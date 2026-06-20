# FIN-REV-020-01 - Defer Policy Settlement Action (M1)

## Status

done — `ApplyDeferFinancePolicyAction` built in isolation (TDD). 10 feature
tests green in `tests/Feature/Finance/Defer/DeferFinancePolicyTest.php`;
settlement keeps finance invariants clean before/after (PRESERVE + FORFEIT).
Not wired into runtime (M3) and charge generation unchanged (M2), per scope.

## Lane

high-risk

## Parent

`FIN-REV-020-defer-finance-settlement` (money slice / Phase 5). This is child M1.
Full domain design lives in `../S-020-defer-finance-settlement/design.md`.

## Depends On

- `FIN-REV-020` slice 1 (itemization + non-billable exceptions + backfill) — done.
- `FIN-REV-002` (ledger source of truth), `FIN-REV-005` (DNG security).

## Current Behavior

`DeferCaseService::processFeePolicy()` only stores `preserve_amount`; it performs
no ledger mutation. There is no code path that releases or consumes real paid
cash when a defer is recorded.

## Target Behavior

A new `ApplyDeferFinancePolicyAction` settles a **FULL-scope PRESERVE/FORFEIT**
defer case using only existing ledger operations (no new ledger, no new table).
This story builds the action **in isolation** — it is NOT wired into the runtime
flow and does not change charge generation (those are M3 / M2).

Settlement model ("void-and-release → re-consume"):

1. Resolve obligations = active positive `finance_charges` for the student +
   defer semester.
2. Auto-safe gate (read-only, before any mutation):
   - live DNG on any obligation → return `skipped: live_dng` (no mutation).
   - discount/scholarship allocation on any obligation line → `skipped: discount_present`.
   - no obligations → `noop: no_charge`.
   - scope ≠ FULL or policy ∉ {PRESERVE, FORFEIT} → `skipped: out_of_scope`.
3. Apply:
   - **PRESERVE**: void each obligation via `VoidFinanceChargeAction(autoReallocate: false)`;
     released paid cash stays unapplied/available. Nothing else.
   - **FORFEIT**: void as above; `consumed = released_paid`. If `consumed > 0`,
     create one `adjustment` charge (`amount = consumed`, `source_type = DeferCase`,
     `source_id = case`) via `CreateFinanceChargeAction`, then allocate `consumed`
     from the student's now-unapplied payments via `AllocatePaymentAction`. If
     `paid = 0` → no adjustment (never create unpaid debt).

## Acceptance Criteria

- `ApplyDeferFinancePolicyAction::handle(DeferCase $case, ?int $userId): array`
  returns a deterministic status (`applied` / `skipped` / `noop`) with reason +
  amounts (released, consumed, voided charge ids, adjustment charge id).
- PRESERVE: obligations voided, paid cash returned to `unapplied_amount`, no
  adjustment charge, no new debt.
- FORFEIT (paid): obligations voided, adjustment charge = paid created and fully
  allocated from released cash; net consumed = paid; no balance beyond paid.
- FORFEIT (unpaid): obligations voided, no adjustment, no debt.
- Auto-safe gate blocks `live_dng` / `discount_present` with NO mutation.
- `finance:audit-invariants` returns 0 offending rows before and after.
- Traceability via existing `source_type`/`source_id` + `void_reason` only.

## Non-Goals

- No runtime wiring (M3) and no charge-generation change (M2).
- No PARTIAL and no COURSE-scope settlement (later slice).
- No new table/column; no DNG payload change; no auto-reallocation.

## Test Plan

| Layer | Cases |
| --- | --- |
| Feature | PRESERVE paid → void + cash available, no adjustment, no debt. |
| Feature | FORFEIT paid → void + adjustment(=paid) allocated, no residual debt. |
| Feature | FORFEIT unpaid → void, no adjustment, no debt. |
| Feature | no charge → `noop`, no mutation. |
| Feature | live DNG obligation → `skipped: live_dng`, charge still active. |
| Feature | discount on obligation → `skipped: discount_present`, no mutation. |
| Platform | `finance:audit-invariants` clean before/after; Pint; `git diff --check`. |

## Commands

```text
./scripts/dev.sh test tests/Feature/Finance/Defer/DeferFinancePolicyTest.php
./scripts/dev.sh artisan finance:audit-invariants
./scripts/dev.sh composer exec pint -- <touched PHP files>
```
