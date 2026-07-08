# 02 — Retake/resit Academic transition integration

**Status:** ready-for-agent
**Depends on:** 01 (debit intake spine)
**PRD:** ../PRD.md · **ADR:** docs/adr/0026-…

## Goal

Retake/resit fees are created eagerly at the Academic chargeable transition, through the intake contract, with no direct Academic→Finance coupling.

## Seam

Existing Academic approve/commit action for `CourseRetakeRegistration` and `ExamResitAttempt` (reuse the current use case; do not add a new entry point).

## Scope

- At the chargeable transition, the Academic action calls `FinanceIntakeContract::request(...)` with source triple + pricing facts (course/unit/attempt/program/semester refs). Source mints `source_ref`.
- **Confirm first:** the exact chargeable transition on each status machine (`approved` vs `approved_waiting_commit`) before wiring — verify against current code.
- Forward path is synchronous: Finance failure fails/rolls back the Academic transition (no "chargeable" state without an obligation).
- Academic keeps `hq_fee_status` as a **local projection** only. Drop use of `finance_charge_id` for the target path. Academic no longer creates or reads Finance charges to drive this transition.
- Retake/resit **cancellation** routes Finance side effects through a Finance-owned cancellation service (Academic never voids charges, mutates invoice lines, recalculates allocation, or cancels DNG).

## Acceptance

- Approving/committing a retake registration (and resit attempt) yields exactly one `FinanceObligation` + charge + line via intake.
- Simulated Finance intake failure → Academic transition rolled back; no orphan and no half-chargeable source.
- Cancelling routes through the Finance cancellation service; Academic performs no direct Finance mutation.
- `finance_charge_id` no longer read on the target path.

## Testing

Feature tests at the Academic action/HTTP seam: approve → obligation exists; forced Finance failure → rollback; cancel → Finance-service invoked, charge voided. Prior art: `ExamResitChargeActionTest`, `tests/Feature/Finance/Cutover/`.

## Out of scope

Settlement/clearance reads (03), DNG (03), backfill (04), dropping the FK column + arch test (05).
