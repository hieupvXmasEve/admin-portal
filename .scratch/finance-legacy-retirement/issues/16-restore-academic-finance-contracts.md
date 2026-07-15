# 16 — Restore Academic–Finance module contracts

Status: completed

## Parent

[Finance Legacy Retirement](../PRD.md)

## What to build

Restore the approved Academic–Finance boundary for retake, resit, and EGC workflows. Finance must consume Academic facts and request Academic state changes through shared contracts rather than querying or mutating Academic Eloquent models directly.

Preserve the existing business outcomes and identifiers while moving ownership of Academic persistence back behind the Academic module boundary.

## Acceptance criteria

- [x] Finance does not directly query, create, update, save, or return Academic-owned Eloquent models for retake registrations, resit attempts, EGC blocks, enrollments, or course facts.
- [x] Shared contracts expose only the minimum stable facts and commands required by supported Finance workflows.
- [x] Academic implementations own transactions and persistence for Academic state; Finance owns obligations, charges, invoices, installments, and collection state.
- [x] Cross-module DTOs or value objects do not leak mutable Eloquent models across the boundary.
- [x] Existing retake creation, resit DNG linking, EGC charge generation, eligibility, idempotency, and error behavior remain unchanged from the user's perspective.
- [x] Contract tests prove the boundary and architecture tests fail on a new direct Finance-to-Academic model dependency.
- [x] Relevant ADR and architecture documentation are updated only where the permanent interface has changed.

## Blocked by

- [Issue 09 — Restore a parseable Finance baseline](09-restore-parseable-finance-baseline.md)

## Implementation notes

- Added `AcademicFinanceChargeSourceGateway`, source-key helpers, and immutable DTOs under `app/Shared/Contracts/Academic`.
- Bound the Academic implementation in `AcademicServiceProvider`; the implementation owns retake/resit/EGC Eloquent reads, state transitions, and Academic transactions.
- Updated Finance retake, resit, DNG, due-reminder, fee-monitor, billing-exception, and EGC workflows to consume contract DTOs/commands instead of Academic source models.
- Updated ADR 0026 to record that EGC block facts/state changes also sit behind the Academic contract while Finance remains the owner of money, obligations, invoices, DNG, and discounts.

## Verification

- `./scripts/dev.sh artisan test tests/Feature/Finance/ExamResitChargeActionTest.php tests/Feature/Finance/Operations/ExamResitDueClassifierTest.php tests/Feature/Finance/Operations/DueReminderExamResitTest.php tests/Feature/Finance/Egc/GenerateEgcChargesTest.php tests/Feature/Finance/Egc/RetakeAdjustmentsTest.php tests/Feature/Finance/Egc/ReconcileEgcChargesAfterSyncTest.php tests/Feature/Finance/Dng/ListDngWorklistQueryTest.php tests/Feature/Finance/Reporting/FeeMonitorRetakeResitInferenceTest.php tests/Feature/Finance/Operations/BillingExceptionsQueryTest.php tests/Feature/Architecture/AcademicFinanceBoundaryArchTest.php tests/Feature/Architecture/AcademicFinanceBoundaryArchRedProofTest.php --compact` — passed, 94 tests / 340 assertions.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` — passed.
- PHP syntax sweep over changed and new PHP files — passed.
- `git diff --check` — passed.
