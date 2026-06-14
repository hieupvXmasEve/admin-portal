# Exec Plan

## Goal

Make Finance balances and invoice snapshots flow from one canonical
ledger-backed calculation.

## Scope

In scope:

- `FIN-01`.
- `FIN-11`.
- Defensive work for `FIN-02` and `FIN-03`.
- `DB-01` immutable `invoice_lines.amount_snapshot`.
- `DB-02` dual-path discount reduction plan.
- `DB-09` operation-level idempotency prescription.
- `DB-13`, `DB-14`, and `DB-15` cache/rail correctness.
- NT4 cache transparency and rebuild path where feasible.
- Tests for settlement, worklist, dashboard, and allocation paths.

Out of scope:

- Final uniqueness/check constraints.
- Scholarship/EGC amount decisions.
- DNG webhook security.
- Full UI redesign.

## Risk Classification

Risk flags:

- Data model.
- Public contracts.
- Existing behavior.
- Weak proof.
- Multi-domain.

Hard gates:

- Money correctness.
- Existing Finance behavior changes.

Portal impact:

- None.

## Work Phases

1. Discovery: list every `deriveInvoiceSnapshot` and charge-centric balance path.
2. Write characterization tests for at least one normal invoice, one discount,
   one reversal/void, and one legacy negative-line case.
3. Introduce or isolate the canonical calculation.
4. Redirect worklist/dashboard/allocation callers to the canonical path.
5. Add allocation row locks and operation-level idempotency at
   `AllocatePaymentAction` / `AutoAllocatePaymentsAction` boundaries.
6. Freeze existing invoice line snapshots on refresh.
7. Make invoice snapshot cache transparent and rebuildable; clear cached
   `paid_at` when reversal removes paid status.
8. Add DNG-vs-ledger reconciliation proof so aggregates cannot double-count the
   provider rail and invoice rail.
9. Add defensive active/status filters without breaking signed reversal rows.
10. Run invariant audit and targeted Finance tests.
11. Update docs and Harness evidence.

## Stop Conditions

Pause for human confirmation if:

- Legacy negative charge handling cannot be removed without data backfill.
- A page intentionally needs a different definition of balance.
- Snapshot immutability would strand known bad data without remediation.
- Allocation idempotency needs a persisted token/migration that belongs in story
  003 before implementation can proceed.
- Product has not decided whether DNG due items and invoice due items can appear
  in the same KPI.
- Validation must be weakened because fixtures cannot represent reversal paths.
