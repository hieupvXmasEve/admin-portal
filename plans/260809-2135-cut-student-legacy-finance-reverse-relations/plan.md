---
title: "Cut Student legacy Finance reverse relations"
description: "Remove Student.php's 3 reverse Finance model relations (financeCharges/payments/invoices), one PR each, replacing sole caller with Finance-owned existence contracts"
status: pending
priority: P1
effort: "3x ~2h"
tags: [finance, student-registry, boundary-cleanup]
created: 2026-08-09
---

# Cut Student legacy Finance reverse relations

## Overview

`app/Models/Student.php` imports 5 Finance models and defines `financeCharges()`,
`payments()`, `invoices()` HasMany relations back into Finance (`billingAccount()`
stays — it's the ADR-0029 payer-identity contract, not legacy). This is
circular legacy↔Finance coupling: Student (legacy core model) reaching into
Finance's table space directly instead of going through a Shared contract.

Scout found exactly **one real caller** for all 3 relations, in one file:
`app/Modules/StudentRegistry/Actions/RequireRevocableStudentIdentityAction.php:18`
(`DOWNSTREAM_ACTIVITY_RELATIONS` array), used only as `$student->{$relation}()->exists()`
inside `run()`. No other app/ or tests/ code calls `->financeCharges(`, `->payments(`,
or `->invoices(` on a `Student` instance (Finance module's own `Payment`/`FinanceCharge`/
`StudentInvoice` `->payments`/`->invoices` etc. are on Finance models, not `Student`,
and are untouched).

`SettlementPositionReader` (mentioned in the task) is scoped to payable-line/invoice/
feeType/billingAccount lookups — **not** a fit for "does this student have any
X" existence checks. The right precedent is `TuitionChargeExistenceReader` +
`TuitionChargeExistenceQuery` (`app/Shared/Contracts/Finance/TuitionChargeExistenceReader.php`,
`app/Modules/Finance/Queries/TuitionChargeExistenceQuery.php`), bound in
`app/Modules/Finance/Providers/FinanceServiceProvider.php`. Each phase below
adds one narrow existence-reader contract + query impl following that exact
pattern, swaps the one caller, then deletes the relation from `Student.php`.

**One relation per PR. Do not combine phases into one diff.**

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Remove `Student::financeCharges()` + `FinanceCharge` import from Student.php | P1 |
| 2 | Remove `Student::payments()` + `Payment` import from Student.php | P1 |
| 3 | Remove `Student::invoices()` + `StudentInvoice` import from Student.php | P1 |

## Phases

| # | Phase | Status |
|---|-------|--------|
| 1 | [Phase 1: Cut Student::financeCharges() reverse relation](./phase-01-start.md) | Completed |
| 2 | [Phase 2: Cut Student::payments() reverse relation](./phase-02-cut-studentpayments-reverse-relation.md) | Completed |
| 3 | [Phase 3: Cut Student::invoices() reverse relation](./phase-03-cut-studentinvoices-reverse-relation.md) | Pending |

## Non-Goals

- `Student::billingAccount()` — stays (ADR-0029 payer identity, not legacy).
- `Student::deferCases()`, `voucherApplications()`, `dngPaymentRequests()` — not
  Finance-model relations on Student in the same way (or out of scope per task ask:
  only `financeCharges()`/`payments()`/`invoices()`).
- No behavior change to the revoke gate itself — same 3 existence checks, same
  error message, same ordering within the loop.

## Success Criteria

- [ ] `app/Models/Student.php` has zero `use App\Modules\Finance\...` imports and
      zero `financeCharges()`/`payments()`/`invoices()` methods (phases 1-3 combined).
- [ ] `RequireRevocableStudentIdentityAction::DOWNSTREAM_ACTIVITY_RELATIONS` no longer
      contains `'financeCharges'`, `'payments'`, `'invoices'` string keys — replaced by
      3 explicit contract calls.
- [ ] Each phase ships as its own PR; `git log` shows 3 separate commits/PRs, not 1.
- [ ] `./scripts/dev.sh artisan test --filter=RequireRevocableStudentIdentityAction`
      (or nearest covering test) green after each phase.

## Validation Log

### Verification Results
- Claims checked: 12 (Standard tier, 3 phases)
- Verified: 12 | Failed: 0 | Unverified: 0
- Tier: Standard
- Key verifications: `FinanceServiceProvider.php` binding block (~line 90) exists and matches described pattern; `Payment::scopeForStudent()` exists at `Payment.php:103` (reused in Phase 2 instead of raw `where()`); `FinanceCharge`/`Payment`/`StudentInvoice` have no `SoftDeletes` trait or visible global scopes; `StudentFinanceController.php:505`'s `->invoices(...)` is a controller-service call, not `Student::invoices()` — confirmed unrelated; sole real caller confirmed as `RequireRevocableStudentIdentityAction.php:18`; existing regression test found at `tests/Feature/StudentApplication/StaffLifecycleTest.php:539` (`financeCharges` only).

### Interview (2 questions)
1. **Test coverage gap (payments/invoices)** — Verified only `financeCharges` has an existing revoke-block test; `payments`/`invoices` have none. Decision: **add 1 new test per phase** (2 and 3) mirroring `StaffLifecycleTest.php:539`'s pattern before cutting each relation. Already reflected in Phase 2/3 Implementation Steps + Success Criteria — no changes needed.
2. **Contract shape** — Decision: **3 separate narrow interfaces** (`StudentFinanceChargeExistenceReader`, `StudentPaymentExistenceReader`, `StudentInvoiceExistenceReader`), matching the `TuitionChargeExistenceReader` precedent, one per phase — no shared file touched across phases. Already reflected in plan — no changes needed.

### Whole-Plan Consistency Sweep
Re-read `plan.md` + all 3 phase files after verification-pass edits. No stale terms, no contradicting claims, no duplicate embedded contract drafts. Test-coverage-gap notes and scope reader are consistent across all 3 phases. Zero unresolved contradictions.

<!-- slug: cut-student-legacy-finance-reverse-relations -->
