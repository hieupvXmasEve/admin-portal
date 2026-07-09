# 05 — Boundary hardening

**Status:** done
**Depends on:** 01, 02, 03, 04
**PRD:** ../PRD.md · **ADR:** docs/adr/0026-…

## Delivery notes (2026-07-09)

- Money models relocated to `App\Modules\Finance\Models`; bulk import update.
- Academic has zero `Finance\Models` imports; fee summary via `StudentFeeSummaryReader`; list/sync/cancel via `ObligationSettlementReader` (+ external-paid helper).
- Pest arch + red-proof under `tests/Feature/Architecture/`.
- `active_source_key` dropped (migration + model).
- Residual (follow-up, not blocking arch green): column drop of `finance_charge_id` on retake/resit tables (deprecated on target path; still present for legacy); morph `source_type`/`source_id` still used by non-intake charge creators; CI D1 re-enable is ops.

## Goal

Make the Academic/Finance boundary namespace-enforced so it cannot silently erode, and retire the legacy coupling now that the target path is live.

## Seam

Pest arch test (or Deptrac) as the enforcement seam; migrations for model relocation + column retirement.

## Scope

- Move money models (`FinanceCharge`, `Payment`, `StudentInvoice`, `InvoiceLine`, …) into `App\Modules\Finance\Models`; update all `use` sites. `Student` stays shared as identity reference.
- Reroute the remaining Academic direct-read leaks (`GetStudentFeeSummaryQuery`, `ListRetakeCourseRegistrationsQuery`, `ListExamResitAttemptsQuery`) through a Finance contract.
- Add a Pest arch test (or Deptrac) forbidding `App\Modules\Academic\* ↔ App\Modules\Finance\Models\*`. Note: it only goes green after 01–04 remove the live coupling, and only bites once CI (`D1`) is re-enabled — flag the CI dependency.
- Retire the legacy source pointers on the target path: drop `finance_charges.active_source_key` after backfill (charge keys on `finance_obligation_id`); drop/deprecate `finance_charge_id` on retake/resit source tables and the polymorphic `source_type`/`source_id` for the target path.

## Acceptance

- Arch test passes on the reworked code and **fails** when a deliberate cross-context model import is introduced.
- No `App\Modules\Academic\*` file imports `App\Modules\Finance\Models\*` (and vice versa).
- `active_source_key` gone; retake/resit source tables carry no Finance id on the target path.
- Full Finance + Academic suites green.

## Testing

Arch test is itself the test. Add a red-proof (temporarily introduce a forbidden import in a test fixture to confirm the arch rule fires). Regression: run existing Finance + Academic feature suites.

## Out of scope

Re-enabling CI workflows (`D1`) is an ops task noted here but tracked separately; credit/discount aggregates (slice-2+).
