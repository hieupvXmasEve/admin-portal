# Overview

## Current Behavior

Finance balance, paid amount, discount amount, and outstanding amount are
derived in multiple places with different rules. The review names this as the
root issue behind `FIN-01`, with defensive gaps in `FIN-02` and `FIN-03`, plus
schema/model drift in `DB-01` and `DB-02`.

Allocation also reads available cash and outstanding amounts without a single
locked boundary (`FIN-11`), so webhook and batch allocation can race. Invoice
cache columns are not named or rebuilt as cache (`DB-14`), `paid_at` can stay
stale after reversal (`DB-15`), and DNG request amounts can be confused with
ledger truth (`DB-13`).

## Target Behavior

All Finance balance-like numbers are derived through one ledger-backed source of
truth. Invoice line snapshots are immutable after creation. Legacy negative
charge fallback and duplicate `deriveInvoiceSnapshot` implementations are
removed or delegated to one canonical path.

Allocation mutates through a row-locked, operation-idempotent boundary. Invoice
cache fields are transparent and rebuildable, and DNG payment requests are
treated as provider rail records that reconcile back to the ledger rather than
as another source of debt truth.

## Affected Users

- Finance staff reading balances, invoices, settlement worklists, and dashboards.
- Developers maintaining Finance settlement.

## Affected Product Docs

- `docs/features/finance/finance-module-review-2026-06-13.md`
- `docs/features/finance/tuition-settlement-model-v2.md`
- `docs/system-architecture.md`
- `docs/codebase-summary.md`
- `docs/code-standards.md`

## Portal Impact

None for this story. Do not change student or lecturer API contracts in this
slice.

## Non-Goals

- Do not add final DB constraints until duplicate data is handled.
- Do not build BOD charts.
- Do not redesign Finance UI except where needed to consume canonical props.
