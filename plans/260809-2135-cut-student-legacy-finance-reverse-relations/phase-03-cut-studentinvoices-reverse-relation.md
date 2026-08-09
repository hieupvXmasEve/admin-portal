---
phase: 3
title: "Cut Student::invoices() reverse relation"
status: pending
priority: P1
effort: "2h"
dependencies: [2]
---

# Phase 3: Cut Student::invoices() reverse relation

## Overview

Same pattern as Phases 1-2, for `invoices()`. Last of 3 — after this lands,
`Student.php` has zero Finance model imports (only `billingAccount()` HasOne
stays, which points at `BillingAccount` and is the deliberate ADR-0029 payer
identity boundary, not being cut). PR #3 of 3.

## Requirements

- Functional: revoke-gate check for "student has any invoice" must produce
  identical pass/fail behavior as `$student->invoices()->exists()`.
- Non-functional: same contract/query/binding pattern as Phases 1-2.

## Architecture

```php
// app/Shared/Contracts/Finance/StudentInvoiceExistenceReader.php
interface StudentInvoiceExistenceReader
{
    public function hasAnyInvoiceFor(int $studentId): bool;
}
```

```php
// app/Modules/Finance/Queries/StudentInvoiceExistenceQuery.php
class StudentInvoiceExistenceQuery implements StudentInvoiceExistenceReader
{
    public function hasAnyInvoiceFor(int $studentId): bool
    {
        return StudentInvoice::query()->where('student_id', $studentId)->exists();
    }
}
```

Note: `StudentFinanceController.php:505` calls `$this->studentFinance->invoices(...)`
— confirm that's a different service method (not the `Student` model relation)
before touching it; scout indicates it's unrelated to this cut (no `->invoices(`
call on a `Student` instance found there).

## Related Code Files

- Create: `app/Shared/Contracts/Finance/StudentInvoiceExistenceReader.php`
- Create: `app/Modules/Finance/Queries/StudentInvoiceExistenceQuery.php`
- Modify: `app/Modules/Finance/Providers/FinanceServiceProvider.php` (bind new contract)
- Modify: `app/Modules/StudentRegistry/Actions/RequireRevocableStudentIdentityAction.php`
  — remove `'invoices'` from `DOWNSTREAM_ACTIVITY_RELATIONS`, add explicit
  `StudentInvoiceExistenceReader` check (same shape as Phases 1-2)
- Modify: `app/Models/Student.php` — delete `invoices()` method (lines ~468-474)
  and `use App\Modules\Finance\Models\StudentInvoice;` import (line 11)

## Implementation Steps

1. Create `StudentInvoiceExistenceReader` contract.
2. Create `StudentInvoiceExistenceQuery` implementation.
3. Bind contract → impl in `FinanceServiceProvider`.
4. In `RequireRevocableStudentIdentityAction::run()`: remove `'invoices'` string,
   add explicit contract check, same `RuntimeException` message.
5. Delete `invoices()` method + `StudentInvoice` import from `Student.php`.
6. Re-grep repo-wide for `->invoices(` on a `Student` instance to confirm no drift.
7. Confirm `Student.php` still has `use App\Modules\Finance\Models\BillingAccount;`
   (untouched — `billingAccount()` is out of scope) and zero remaining
   `FinanceCharge`/`Payment`/`StudentInvoice` imports.
8. **Verified gap:** same as Phase 2 — no existing test exercises the `invoices`
   branch. Add one test in `tests/Feature/StudentApplication/StaffLifecycleTest.php`
   mirroring Phase 1's `financeCharges` test (`StaffLifecycleTest.php:539`) but
   creating a `StudentInvoice` row instead, asserting the same
   `assertSessionHas('error')` revoke-block behavior.
9. Run `tests/Feature/StudentApplication/StaffLifecycleTest.php` (full file).
   This is the last phase — confirm all 3 revoke-block tests (financeCharges,
   payments, invoices) are green together.

## Success Criteria

- [ ] `Student.php` has no `invoices()` method, no `StudentInvoice` import.
- [ ] `Student.php` has zero remaining `use App\Modules\Finance\...` imports for
      `FinanceCharge`, `Payment`, `StudentInvoice`, `DngPaymentRequest` (confirm
      `DngPaymentRequest`/`dngPaymentRequests()` was out of scope per task — leave
      as-is unless user scope expands).
- [ ] `RequireRevocableStudentIdentityAction::DOWNSTREAM_ACTIVITY_RELATIONS` no
      longer contains `financeCharges`/`payments`/`invoices` — all 3 replaced by
      explicit contract calls.
- [ ] `grep -rn "->invoices(" app --include="*.php"` returns zero matches outside
      `app/Modules/Finance/`.
- [ ] New `invoices`-branch revoke-block test passes; all 3 revoke-block tests green together.
- [ ] Full plan Success Criteria in `plan.md` all checked.

## Risk Assessment

- Same low blast radius as Phases 1-2.
- Verified: `StudentFinanceController.php:505`'s `->invoices(...)` call is
  `$this->studentFinance->invoices(...)` (a controller service method,
  `StudentFinanceController::invoices()`), not `Student::invoices()` — confirmed
  unrelated, do not touch that call site.
- Verified: `StudentInvoice` model has no `SoftDeletes` trait and no visible
  global scopes — raw `where('student_id', ...)->exists()` is equivalent to the
  old `hasMany()->exists()`.
