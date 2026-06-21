# Exec Plan

## Goal

When EGC block results sync confirms a `fail`, automatically relevel
pre-generated future EGC blocks/charges to the correct retake sequence and
auto-apply the 50% retake discount when attendance ≥ 80%, including paid
invoice settlement handling.

## Scope

In scope:

- `ReconcileEgcChargesAfterSyncAction` + unit/integration tests
- Hook from `SyncEgcBlockResultsAction` (auto run, no second staff click)
- In-place mutation of linked charges + invoice line snapshots
- Reuse `ApplyEgcRetakeDiscountAction` for discount creation
- Paid invoice overpayment handling via `SettlementService`
- Combined Finance Office UI that merges Block Results sync and Retake
  Adjustments outcomes into one post-sync reconciliation workflow
- Sidebar entry in Finance Office (New UI) for the combined EGC reconciliation
  workflow
- Legacy Block Results / Retake Adjustments route handling as redirects or
  section-focused deep links during migration
- Update openspec EGC retake/block specs to document auto path

Out of scope:

- Academic progression changes
- Batch Studio generation preview changes (may add warning in follow-up)
- Carry Forward rework
- Student/lecturer portal API changes
- Manual early major entry credit UI

## Risk Classification

Risk flags:

- data-model (egc_blocks, finance_charges, invoice_lines)
- audit/security (financial writes triggered by sync)
- existing behavior (retake discount moves from manual-first to auto-first)
- weak proof (paid invoice paths need new fixtures)
- multi-domain (Finance only, but touches settlement)

Hard gates:

- data loss / paid invoice mutation — requires invariant audit + transactional
  per-student writes

## Work Phases

1. **Discovery** — confirm relevel algorithm on dev fixtures (`AUS112882`,
   single-block L5 case); document skip reasons.
2. **Design** — finalize planner + safety guards; confirm permission model
   (`sync_egc_block_results` covers financial writes).
3. **TDD** — unit tests for planner/guards; integration tests Fixtures A–D.
4. **Implement** — Action + sync hook + UI summary.
5. **Verify** — targeted Pest, `finance:audit-invariants`, pint, type-check.
6. **Harness** — trace + update epic README row; optional ADR if manual-only D3
   is superseded.

## Implementation Order

1. Pure planner value object / private methods + unit tests
2. Relevel writer (charge + invoice line) + tests (draft invoice)
3. Paid invoice + settlement integration tests
4. Auto-discount wiring through `ApplyEgcRetakeDiscountAction`
5. Sync hook + response payload
6. Vue combined EGC reconciliation page: sync trigger, result summary,
   eligible/applied/manual-repair sections
7. Sidebar migration into Finance Office (New UI) and legacy route redirect or
   deep-link behavior

## Stop Conditions

Pause for human confirmation if:

- In-place charge mutation is unsafe for a discovered invoice pattern (multi-line
  bundles, mixed fee categories on one invoice).
- `finance:audit-invariants` fails on Fixture A and cannot be fixed within story
  scope.
- Product wants reconcile as opt-in toggle rather than automatic (contradicts
  current decision — escalate).

## Harness Delta

- Register story `FIN-REV-021-egc-post-sync-charge-reconciliation`
- Add row to `E-finance-module-review-2026-06/README.md`
- Trace after story file creation; trace again after implementation
