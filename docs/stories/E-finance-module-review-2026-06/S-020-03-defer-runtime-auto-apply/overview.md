# FIN-REV-020-03 - Runtime Auto-Apply Defer Settlement (M3)

## Status

done (2026-06-21, TDD)

Implemented: `RecordStudentActionAction` auto-applies `ApplyDeferFinancePolicyAction`
in the same transaction as case creation for FULL-scope PRESERVE/FORFEIT
**non-EGC** defers (EGC defers keep their existing `createEgcDeferCredits`
offset path). The action self-gates needs-review cases (live DNG / discount /
non-FULL/PARTIAL) → recorded, no money mutation; idempotent on re-record.
`finance:defer-backfill --apply-money` settles historical auto-safe cases via
the same action. Fixed a latent M1 bug: the no-auth (console) actor was cast
`(int) null → 0` and tripped the `payment_applications.created_by` FK;
`AllocatePaymentAction::run` now accepts a nullable `?int $userId` (system
actor). Tests: `DeferRuntimeAutoApplyTest` (5, green); EGC defer + full Defer
suite + allocate-using exam-resit test green; `finance:audit-invariants` clean
in-test and at dev baseline.

## Lane

high-risk

## Parent

`FIN-REV-020-defer-finance-settlement` (money slice). Child M3.

## Depends On

- `FIN-REV-020-01` (settlement action), `FIN-REV-020-02` (defer-aware generation).

## Current Behavior

`RecordStudentActionAction` creates the defer case + items (slice 1) but applies
no money. The settlement action from M1 exists but is not invoked anywhere.

## Target Behavior

Per the confirmed product decision (runtime auto-apply): when staff record a
**FULL-scope PRESERVE/FORFEIT** academic defer, the settlement runs automatically
for auto-safe cases inside the same transaction as case creation.

- Auto-safe case → `ApplyDeferFinancePolicyAction` runs (void-and-release;
  FORFEIT consume).
- Needs-review case (`live_dng` / `discount_present` / ambiguous) → defer case +
  items are still recorded, but NO money mutation; the case is left for the
  operator dry-run/review queue.
- Idempotent: re-recording / action retry does not double-settle (a settled
  case's obligations are already void; the action is a no-op the second time).
- Also expose money apply for historical auto-safe cases via the backfill
  command (`finance:defer-backfill --apply-money`), reusing the same action.

## Acceptance Criteria

- Recording a FULL PRESERVE defer with a paid charge → obligation voided, paid
  cash preserved/available; no debt; defer case + items recorded.
- Recording a FULL FORFEIT defer with a paid charge → obligation voided +
  adjustment(=paid) allocated; no residual debt.
- Needs-review defer (live DNG / discount / partial) → case + items recorded,
  money untouched, flagged for review.
- Re-running the same defer action is idempotent (no second settlement).
- `finance:audit-invariants` clean after runtime recording.
- Student/lecturer portal API unchanged.

## Non-Goals

- No PARTIAL / COURSE-scope auto-apply (still report-only).
- No re-enrollment behavior (M4).

## Test Plan

| Layer | Cases |
| --- | --- |
| Feature | Runtime FULL PRESERVE paid → settled (void + preserved). |
| Feature | Runtime FULL FORFEIT paid → settled (void + adjustment allocated). |
| Feature | Runtime needs-review (live DNG/discount) → recorded, no money mutation. |
| Feature | Action retry idempotent (no double settlement). |
| Feature | `finance:defer-backfill --apply-money` settles historical auto-safe case. |
| Platform | `finance:audit-invariants`; Pint; `git diff --check`. |

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Defer/DeferRuntimeAutoApplyTest.php
./scripts/dev.sh test tests/Feature/Academic/StudentActionEgcDeferBlockTest.php
./scripts/dev.sh artisan finance:audit-invariants
```
