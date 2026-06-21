# Design

## Problem Statement

EGC billing intentionally runs ahead of academic results. When a later sync
confirms `fail` on level *N*, pre-generated blocks in semester *S+1* may point at
levels *N+1*, *N+2* instead of the correct retake sequence *N*, *N+1*. The
current system leaves finance and academics diverged until staff manually apply
retake discounts against a same-level target charge that does not exist.

## Domain Model

### Entities (existing)

- `egc_blocks` — billing position per semester (`block_number`), academic level
  (`level_number`), outcome (`result`, `attendance_rate`), retake entitlement
  (`retake_discount_id`, `is_retake`), charge link (`finance_charge_id`)
- `finance_charges` — `TYPE_EGC_LEVEL_FEE`
- `invoice_lines`, `invoice_discounts`, `discount_allocations`
- `egc_retake_discount_links` — source block → target charge audit + uniqueness

### New concepts

- **Reconciliation plan** — pure in-memory structure per student describing
  source fail block(s), target pending blocks to relevel, expected levels, and
  whether auto-discount should run.
- **Reconciliation outcome** — persisted writes + audit metadata returned to
  sync UI.

## Reconciliation Rules

### Trigger

Run after `SyncEgcBlockResultsAction` completes for a semester, scoped to
students whose blocks in that semester changed to `result = fail` during the
sync **or** whose fail result was already `fail` but downstream pending blocks
are still mis-leveled.

Idempotency: a second sync/reconcile with no level drift must be a no-op.

### Source eligibility (retake entitlement)

A source block qualifies when:

- `result = fail`
- `finance_charge_id IS NOT NULL` (non-deferred)
- `retake_discount_id IS NULL`
- Semester is within retake-discount policy window (SPRING2026 onward per D7)

Auto-discount additionally requires `attendance_rate ≥ 80`.

### Target selection

Candidate targets are **pending** `egc_blocks` in semesters after the source
semester, ordered by `(semester_id, block_number)`, that:

- have `finance_charge_id IS NOT NULL`
- have `result = pending`
- have an active charge with exactly one active invoice line
- do not already have an `egc_retake_discount_links` row

Same-semester later `block_number` targets remain supported for within-semester
retake patterns.

### Expected level sequence

Given:

- `failed_level = source.level_number`
- `targets = [t1, t2, ...]` pending blocks after source, sorted

Assign:

```
expected_levels[i] = failed_level + i   // i = 0..count(targets)-1
```

Examples:

| Pre-generated | After fail | Reconciled |
| --- | --- | --- |
| L4, L5 (2 blocks) | fail L3 | L3 retake, L4 |
| L5 (1 block) | fail L4 | L4 retake |
| L3, L4 (2 blocks) | fail L2 | L2 retake, L3 |

Retake flag:

- `is_retake = true` on the first reconciled block whose `expected_level ==
  failed_level`.
- Subsequent blocks in the sequence: `is_retake = false` unless a separate fail
  entitlement exists for that level.

### Relevel writes

For each target where `target.level_number != expected_level`:

1. Update `egc_blocks.level_number` and `is_retake`.
2. Update linked `finance_charges`:
   - `description = "EGC Level {expected_level} Fee"`
   - `amount = EgcLevelFeeResolver::resolve(expected_level)`
3. Update active `invoice_lines` snapshots for that charge.
4. Recalculate invoice totals.

Do **not** create a second charge for the same block; mutate the existing
charge/line in place to preserve payment history.

### Auto discount

After releveling, if source is discount-eligible:

1. Identify the reconciled target block where `level_number = failed_level`.
2. Call `ApplyEgcRetakeDiscountAction` with
   `source_egc_block_id` + `target_finance_charge_id`.
3. This creates `InvoiceDiscount` (7_500_000), allocation, link row, sets
   `retake_discount_id`, and runs paid-invoice overpayment release when needed.

If attendance `< 80%`, relevel still runs but discount is skipped.

### Paid invoice / settlement

When relevel reduces a line amount or applies discount on a paid invoice:

- Use existing `SettlementService::releaseLineOverpayment` semantics.
- Record reconciliation audit entries (see Observability).
- If payment applications cannot be safely auto-adjusted (for example multiple
  active lines, existing non-retake discounts, split payments), mark student as
  `needs_manual_repair` and **do not** partially write.

## Application Flow

### New Action

`ReconcileEgcChargesAfterSyncAction`

Location: `app/Modules/Finance/Actions/Egc/`

Responsibilities:

- Input: `semesterId` (synced semester), optional `studentIds` filter
- Build reconciliation plans
- Validate safety preconditions per student
- Execute relevel + optional auto-discount in `DB::transaction()` per student
- Return summary:
  - `releveled_blocks`
  - `discounts_applied`
  - `settlement_releases`
  - `skipped`
  - `needs_manual_repair` with reasons

### Integration point

Preferred: invoked at end of `SyncEgcBlockResultsAction::run()` after block
results are persisted.

Alternative (if blast radius too large for one PR): dedicated
`POST /finance/egc/block-results/reconcile` — **not preferred** because product
asked for auto reconcile; default path should not require a second click.

### Query updates

`ListEgcRetakeAdjustmentsQuery`:

- Continue listing manual-repair cases.
- Exclude blocks already auto-reconciled (`retake_discount_id` set) from
  eligible groups (already true).
- Optionally add `reconciled_by = auto|manual` if audit column/log is added.

## Interface Contract

### Web

- `POST finance.egc.block-results.sync` response / flash payload extended with
  reconciliation summary counts.
- New primary Finance Office surface for EGC post-sync reconciliation. It
  replaces the operator need to move between `finance/egc/block-results` and
  `finance/egc/retake-adjustments` for the same feature.
- The combined surface shows:
  - semester selector + Block Results sync trigger
  - sync/reconciliation summary counts
  - synced block-result rows and changed result/attendance state
  - eligible retake targets, applied/manual discounts, auto-reconciled rows, and
    `needs_manual_repair` rows
  - per-row actions gated by existing permissions (`sync_egc_block_results`,
    `apply_egc_retake_adjustment`)
- Legacy routes should continue to resolve during migration:
  - `finance.egc.block-results.index` may redirect to the combined surface with
    the sync/results section focused.
  - `finance.egc.retake-adjustments.index` may be the first implementation of
    the combined surface or redirect to it once a new route name exists.
- Finance Office (New UI) sidebar exposes one `EGC · Kết quả & học lại` entry
  under `Sinh phí`; the old two-link Legacy sidebar pattern is not the primary
  UX.

### Permissions

Reuse:

- `sync_egc_block_results` — triggers reconcile
- `apply_egc_retake_adjustment` — not required for auto path if reconcile is
  nested under sync permission; document that sync now performs financial
  writes.

Decision: auto reconcile inherits `sync_egc_block_results` but should log
`auth()->id()` on discounts/charges same as manual apply.

## Data Model

No new tables required for MVP.

Optional additive column (future slice, not blocking):

- `egc_blocks.reconciled_at` / `reconciled_by_user_id`
- or append to existing audit log table

`egc_retake_discount_links` remains the discount audit source of truth.

## UI / Platform Impact

- `resources/js/pages/Finance/EgcOperations/BlockResults.vue` and
  `resources/js/pages/Finance/EgcOperations/RetakeAdjustments.vue` should be
  consolidated into one post-sync reconciliation page or one of them should
  become the canonical page while the other redirects/focuses a section.
- Use the existing queries/actions as building blocks instead of duplicating
  reconciliation logic in the page.
- The table/filter implementation must follow the current Finance UI pattern
  (`useDataTable` for list/filter/pagination pages).
- `resources/js/constants/menu-sidebar.ts` should advertise the combined
  Finance Office entry; Carry Forward remains separate.

Portal impact: none.

## Observability

Log per student reconciliation with:

- `source_egc_block_id`
- `target_block_ids`
- `old_levels` → `new_levels`
- `discount_applied` boolean + `invoice_discount_id`
- `released_amount` when settlement runs
- `status = applied | skipped | needs_manual_repair`

Sync flash example:

> Synced 42 blocks. Reconciled 3 students (5 blocks releveled, 2 auto discounts).

## Safety Guards (stop conditions)

Skip auto reconcile for a student when any of:

- Target charge has more than one active invoice line
- Target charge already has a non-`egc_retake` discount allocation
- Target charge already linked in `egc_retake_discount_links`
- Source block already has `retake_discount_id`
- Charge status is not `active`
- Invoice is void/cancelled
- Expected level would exceed `gc_total_levels`

Return structured reason for staff repair via Retake Adjustments or Student 360.

## Alternatives Considered

1. **Manual-only Retake Adjustments with staff relevel tool** — rejected; product
   requires auto reconcile on paid invoices.
2. **Void L4/L5 and regenerate L3/L4 charges** — rejected; breaks payment trace
   on paid invoices.
3. **Block charge generation until prior semester sync completes** — rejected;
   conflicts with business rule allowing early charge generation.

## Reference Fixture

`AUS112882` / student id `502`:

- Source: block 143, SPRING2026, fail L3, 97.14%
- Targets: blocks 326/327, SUMMER2026, currently L4/L5, invoice paid
- Expected: 326→L3 retake + auto 7.5M discount, 327→L4, settlement release as
  needed
