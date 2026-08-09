---
phase: 2
title: "Cut Student::payments() reverse relation"
status: completed
priority: P1
effort: "2h"
dependencies: [1]
---

# Phase 2: Cut Student::payments() reverse relation

## Overview

Same pattern as Phase 1, for `payments()`. Depends on Phase 1 landing first
(sequential PRs per task instruction: "không cắt cả 3 cùng lúc" — not
technically coupled, but keeps caller-file diffs small and reviewable one at
a time). PR #2 of 3.

## Requirements

- Functional: revoke-gate check for "student has any payment" must produce
  identical pass/fail behavior as `$student->payments()->exists()`.
- Non-functional: same contract/query/binding pattern as Phase 1.

## Architecture

```php
// app/Shared/Contracts/Finance/StudentPaymentExistenceReader.php
interface StudentPaymentExistenceReader
{
    public function hasAnyPaymentFor(int $studentId): bool;
}
```

```php
// app/Modules/Finance/Queries/StudentPaymentExistenceQuery.php
class StudentPaymentExistenceQuery implements StudentPaymentExistenceReader
{
    public function hasAnyPaymentFor(int $studentId): bool
    {
        return Payment::query()->forStudent($studentId)->exists();
    }
}
```

Verified: `Payment.php:103` already has `scopeForStudent($query, int $studentId)`
→ `$query->where('student_id', $studentId)`. Reuse it instead of a raw `where()`:
`Payment::query()->forStudent($studentId)->exists()`.

## Related Code Files

- Create: `app/Shared/Contracts/Finance/StudentPaymentExistenceReader.php`
- Create: `app/Modules/Finance/Queries/StudentPaymentExistenceQuery.php`
- Modify: `app/Modules/Finance/Providers/FinanceServiceProvider.php` (bind new contract)
- Modify: `app/Modules/StudentRegistry/Actions/RequireRevocableStudentIdentityAction.php`
  — remove `'payments'` from `DOWNSTREAM_ACTIVITY_RELATIONS`, add explicit
  `StudentPaymentExistenceReader` check (same shape as Phase 1's financeCharges check)
- Modify: `app/Models/Student.php` — delete `payments()` method (lines ~452-458)
  and `use App\Modules\Finance\Models\Payment;` import (line 10)

## Implementation Steps

1. Create `StudentPaymentExistenceReader` contract.
2. Create `StudentPaymentExistenceQuery` implementation using `Payment.php:103`'s
   existing `scopeForStudent` (`Payment::query()->forStudent($studentId)->exists()`).
3. Bind contract → impl in `FinanceServiceProvider`.
4. In `RequireRevocableStudentIdentityAction::run()`: remove `'payments'` string,
   add explicit contract check with the same `RuntimeException` message.
5. Delete `payments()` method + `Payment` import from `Student.php`.
6. Re-grep repo-wide for `->payments(` on a `Student` instance to confirm no
   drift since scout (none found originally).
7. **Verified gap:** no existing test exercises the `payments` branch of the
   revoke-gate loop (only `financeCharges` has direct coverage — see Phase 1's
   `StaffLifecycleTest.php:539`). Add one test in
   `tests/Feature/StudentApplication/StaffLifecycleTest.php` mirroring that test
   but creating a `Payment` row instead of `FinanceCharge`, asserting the same
   `assertSessionHas('error')` revoke-block behavior.
8. Run `tests/Feature/StudentApplication/StaffLifecycleTest.php` (full file,
   including the new test from step 7).

## Success Criteria

- [x] `Student.php` has no `payments()` method, no `Payment` import.
- [x] `RequireRevocableStudentIdentityAction` behavior unchanged.
- [x] `grep -rn "->payments(" app --include="*.php"` returns zero matches outside
      `app/Modules/Finance/`.
- [x] New `payments`-branch revoke-block test passes; existing `StaffLifecycleTest.php` green.

## Risk Assessment

- Same low blast radius as Phase 1.
- Verified: `Payment` model has no `SoftDeletes` trait and no visible global
  scopes — `scopeForStudent()`-based `exists()` is behavior-equivalent to the
  old `hasMany()->exists()`.
- Risk: this relation had **no direct test coverage** before this phase (only
  `financeCharges` was tested) — the new test in step 7 closes that gap, but
  means there's no pre-existing red/green signal to lean on; write it carefully
  against the exact same assertion shape as the `financeCharges` test.
