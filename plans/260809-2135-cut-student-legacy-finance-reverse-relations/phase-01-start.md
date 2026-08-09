---
phase: 1
title: "Cut Student::financeCharges() reverse relation"
status: completed
priority: P1
effort: "2h"
dependencies: []
---

# Phase 1: Cut Student::financeCharges() reverse relation

## Overview

Add a `FinanceChargeExistenceReader` contract (Finance-owned), implement it,
swap the sole caller (`RequireRevocableStudentIdentityAction`) to use it, then
delete `Student::financeCharges()` and its `FinanceCharge` import. PR #1 of 3
— `payments()`/`invoices()` untouched in this PR.

## Requirements

- Functional: revoke-gate check for "student has an active/any finance charge"
  must produce identical pass/fail behavior as `$student->financeCharges()->exists()`.
- Non-functional: follow `TuitionChargeExistenceReader` pattern exactly — contract
  in `Shared/Contracts/Finance`, query impl in `Modules/Finance/Queries`, bound
  in `FinanceServiceProvider`.

## Architecture

Mirror `app/Shared/Contracts/Finance/TuitionChargeExistenceReader.php` /
`app/Modules/Finance/Queries/TuitionChargeExistenceQuery.php`, but single-student
existence (not batch semester check) — signature: `hasAnyChargeFor(int $studentId): bool`.

```php
// app/Shared/Contracts/Finance/StudentFinanceChargeExistenceReader.php
interface StudentFinanceChargeExistenceReader
{
    public function hasAnyChargeFor(int $studentId): bool;
}
```

```php
// app/Modules/Finance/Queries/StudentFinanceChargeExistenceQuery.php
class StudentFinanceChargeExistenceQuery implements StudentFinanceChargeExistenceReader
{
    public function hasAnyChargeFor(int $studentId): bool
    {
        return FinanceCharge::query()->where('student_id', $studentId)->exists();
    }
}
```

Bind in `app/Modules/Finance/Providers/FinanceServiceProvider.php` next to the
existing `TuitionChargeExistenceReader` binding (~line 90).

## Related Code Files

- Create: `app/Shared/Contracts/Finance/StudentFinanceChargeExistenceReader.php`
- Create: `app/Modules/Finance/Queries/StudentFinanceChargeExistenceQuery.php`
- Modify: `app/Modules/Finance/Providers/FinanceServiceProvider.php` (bind new contract)
- Modify: `app/Modules/StudentRegistry/Actions/RequireRevocableStudentIdentityAction.php`
  — remove `'financeCharges'` from `DOWNSTREAM_ACTIVITY_RELATIONS`, inject/resolve
  `StudentFinanceChargeExistenceReader`, add explicit check before/after the loop
  (loop stays generic-relation-driven for the remaining string-keyed relations;
  this one becomes an explicit contract call)
- Modify: `app/Models/Student.php` — delete `financeCharges()` method (lines ~436-442)
  and `use App\Modules\Finance\Models\FinanceCharge;` import (line 9)

## Implementation Steps

1. Create `StudentFinanceChargeExistenceReader` contract.
2. Create `StudentFinanceChargeExistenceQuery` implementation (`FinanceCharge::query()->where('student_id', $id)->exists()`).
3. Bind contract → impl in `FinanceServiceProvider`.
4. In `RequireRevocableStudentIdentityAction::run()`: remove `'financeCharges'` string
   from `DOWNSTREAM_ACTIVITY_RELATIONS`; add explicit
   `if (app(StudentFinanceChargeExistenceReader::class)->hasAnyChargeFor($student->id)) { throw ... }`
   using the exact same `RuntimeException` message as the existing block, before
   or interleaved with the relation loop — order doesn't affect behavior since
   all checks throw the same message.
5. Delete `financeCharges()` method + `FinanceCharge` import from `Student.php`.
6. Grep repo-wide for any other `->financeCharges(` on a `Student` instance (scout
   found none outside this file — re-verify at implementation time in case of drift).
7. Run `tests/Feature/StudentApplication/StaffLifecycleTest.php` — verified
   coverage exists at line 539: `it('blocks revoke when Finance activity exists
   without deleting any owner record')`, which creates a `FinanceCharge` row
   directly and asserts the revoke POST returns `assertSessionHas('error')`.
   This is this phase's exact regression test.

## Success Criteria

- [x] `Student.php` has no `financeCharges()` method, no `FinanceCharge` import.
- [x] `RequireRevocableStudentIdentityAction` behavior unchanged (same exception,
      same trigger condition) — verified by existing/covering tests green.
- [x] `grep -rn "->financeCharges(" app --include="*.php"` returns zero matches
      outside `app/Modules/Finance/`.
- [x] `./scripts/dev.sh artisan test` for touched test files green.

## Risk Assessment

- Low blast radius: single caller, existence-only check, no data mutation.
- Verified: `FinanceCharge` model has no `SoftDeletes` trait and no visible global
  scopes (checked `use` imports) — raw `FinanceCharge::query()->where('student_id', $id)->exists()`
  is behavior-equivalent to the old `hasMany()->exists()`.
