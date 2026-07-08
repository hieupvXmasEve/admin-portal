# 05 — Boundary hardening

**Status:** ready-for-agent
**Depends on:** 01, 02, 03, 04
**PRD:** ../PRD.md · **ADR:** docs/adr/0026-…

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
