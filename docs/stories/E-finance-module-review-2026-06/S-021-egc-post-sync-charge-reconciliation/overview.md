# FIN-REV-021 EGC Post-Sync Charge Reconciliation

## Status

implemented

## Lane

high-risk

## Portal Impact

none

## Current Behavior

EGC charge generation is allowed **before** prior-semester block results are
final. `GenerateEgcChargesAction` projects the next one or two levels from
`gc_current_level` and creates `egc_blocks` + `finance_charges` at full price.

`SyncEgcBlockResultsAction` only writes `result`, `attendance_rate`, and
`synced_at` on `egc_blocks`. It does **not** adjust later-semester blocks or
charges when a prior block is discovered to be `fail`.

Retake discount is a **manual** staff action on `/finance/egc/retake-adjustments`.
The page expects a later block at the **same level** as the failed source block.
When charges were generated too far ahead (for example L4 + L5 while the student
later fails L3), the UI shows **Waiting for target charge** even though the
student is academically retaking the failed level.

Observed case: `AUS112882`

- SPRING2026 block 143: FAIL Level 3, attendance 97.14% (eligible).
- SUMMER2026 blocks/charges were generated as Level 4 + Level 5 before the fail
  was synced.
- Academic registration in SUMMER2026 is EGC3 (Level 3 retake).
- Invoice is already **paid**, so staff cannot practically fix this by manual
  retake adjustment alone.

Carry Forward does not solve this because it matches blocks to registrations by
position (`block_number`), not by reconciled academic level, and it does not
relevel charges or auto-apply retake discounts.

## Target Behavior

After EGC block-result sync, the Finance module **automatically reconciles**
future pending EGC blocks/charges when a newly confirmed `fail` block proves
that pre-generated levels were too high.

### Reconciliation outcomes

1. **Relevel pending blocks** in semesters after the failed source semester
   (and, when applicable, later blocks in the same semester) so billing
   sequence matches the retake path.
   - Example: fail L3 with pre-generated L4 + L5 → reconcile to L3 (retake) +
     L4.
   - Example: 1-block semester pre-generated as L5 but student failed L4 →
     reconcile the single block to L4 retake.
2. **Update linked finance artifacts** for each releveled block:
   - `egc_blocks.level_number`, `is_retake`
   - `finance_charges.description`, `amount` (via `EgcLevelFeeResolver`)
   - `invoice_lines.amount_snapshot`, `description_snapshot`
   - `student_invoices.recalculateTotals()`
3. **Auto-apply 50% retake discount** when the source fail block has
   `attendance_rate ≥ 80%` and `retake_discount_id IS NULL`, using the
   reconciled retake charge as the target.
4. **Handle paid invoices** through the existing settlement path
   (`SettlementService::releaseLineOverpayment`) when discount or releveling
   creates overpayment.

### Operator experience

- Block Results sync and Retake Adjustments become **one Finance Office
  post-sync reconciliation surface** instead of two separate operator pages.
  Staff should not have to sync results on `/finance/egc/block-results`, then
  navigate to `/finance/egc/retake-adjustments` to see the consequence.
- The combined surface contains the sync trigger, sync/reconcile summary,
  newly changed block rows, eligible retake targets, applied/auto-reconciled
  rows, and manual-repair rows in one workflow.
- Reconciliation runs automatically as part of sync (same transaction boundary
  per student or explicit follow-up action — see design).
- Retake Adjustments remains available for audit/repair, but the common
  pre-paid/pre-generated case should land in **Applied** (or a new
  **Auto-reconciled** section), not **Waiting for target charge**.
- Sync summary / flash message reports reconciled students: releveled blocks,
  auto discounts applied, settlement releases, and rows needing manual repair.
- Finance Office (New UI) must expose the combined EGC reconciliation entry
  under `Sinh phí`; the old direct links may remain as deep links or redirects
  during migration, but they should not be the primary sidebar experience.

## Affected Users

- HQ/Finance staff: sync block results without a second manual relevel +
  discount workflow for the common “charges generated early” case.
- EGC students: invoice lines and discounts reflect the actual retake level
  after results are known.

## Affected Product Docs

- `openspec/changes/egc-fee-management/design.md` (D3 retake discount — add auto
  reconcile path; manual path remains for repair)
- `openspec/changes/egc-fee-management/specs/egc-retake-adjustments/spec.md`
- `openspec/changes/egc-fee-management/specs/egc-block-tracking/spec.md`
- `docs/stories/E-finance-module-review-2026-06/README.md`

## Non-Goals

- Changing academic grading or progression rules.
- Replacing EGC Carry Forward for transitioned/major students. Carry Forward is
  a separate workflow and is not part of the Block Results + Retake Adjustments
  merge.
- Auto-reconciling non-EGC retake/resit fees (`retake_fee`, `exam_resit_fee`).
- Student or lecturer portal API changes.

## Depends On

- `GenerateEgcChargesAction`, `SyncEgcBlockResultsAction`,
  `ApplyEgcRetakeDiscountAction`, `SettlementService`
- Preferably `FIN-REV-002` ledger/settlement truth and `FIN-REV-004` discount
  correctness conventions, but this story may proceed with targeted invariant
  evidence if those slices are already stable in dev.
