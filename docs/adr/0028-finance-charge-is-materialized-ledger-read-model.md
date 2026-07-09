# FinanceCharge is a materialized ledger read model, permanently

**Status:** Accepted
**Date:** 2026-07-09
**Owner:** Finance

## Context

After the Finance Obligation v2 migration, obligations and entitlements are the
source of truth for why money is owed or reduced. `finance_charges` and
`invoice_lines` are woven deep into settlement math, installments, DNG pivots,
Fee Monitor, reports, and CSV exports. The question was whether the migration
endgame deletes `finance_charges` (aggregates become the ledger directly) or
keeps it.

## Decision

`FinanceCharge` (with `InvoiceLine`) stays **permanently** as the materialized
ledger read model **for debit obligations only**. After the hardening wave:

- The **only** writer is the Finance materializer, which projects accepted
  **debit** obligations into charge/line rows. Credit and discount entitlements
  never materialize charge rows (see ADR-0030). Direct
  `FinanceCharge::create()` in production app code anywhere else fails an
  architecture test; tests use dedicated ledger fixture helpers instead of
  reaching around the materializer.
- `source_type`, `source_id`, and `active_source_key` are dropped; a charge
  knows only its `finance_obligation_id`. Source identity lives on the
  obligation via the Obligation Source Reference. Credit/discount entitlement
  effects live in their own carriers, not on charge rows.
- Settlement, DNG, Fee Monitor, reports, and exports keep reading
  `finance_charges` unchanged.

## Why

Deleting the table would force a second full rewrite of settlement, DNG, and
every report after the migration, for near-zero business gain. What must die is
`FinanceCharge` as an **integration point** (external modules creating or
correlating with charges), not the table. "Read model forever" is the honest
end-state: truth upstream, ledger substrate downstream.

## Consequences

- If a materialized charge disagrees with its aggregate + settlement ledger,
  the aggregate/ledger wins and the charge is re-projected.
- **No new negative charge rows.** New credit flows carry their reduction
  through the credit-application ledger (ADR-0030); legacy negative rows are
  converted per migration wave and the settlement fallback that nets negative
  lines is deleted at hardening, once zero active negative lines remain.
- Converted legacy negative rows may remain as voided historical artifacts with
  migration provenance, but they are not part of the active debit read model and
  do not require an entitlement foreign key on `finance_charges`.
- No new column on `finance_charges` may carry business meaning that is absent
  from the owning aggregate.
