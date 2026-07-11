# FinanceCharge is a materialized ledger read model, permanently

**Status:** Accepted
**Date:** 2026-07-09
**Last updated:** 2026-07-11
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
- The **Payable Line** is the atomic settlement unit. Cash, discount, and credit
  applications reduce individual lines; every charge, invoice, billing-account,
  DNG, report, or staff-facing collectible total aggregates those derived line
  positions. Consumers must not derive collectible debt independently from a
  gross charge amount or cached invoice total. Each derived position preserves
  gross, discount, applied cash, applied credit, and remaining collectible as
  separate values; applied credit is never reported as cash paid.
- Settlement, DNG, Fee Monitor, reports, and exports keep reading the
  materialized Settlement Ledger rather than the upstream aggregates, but they
  consume canonical derived line positions instead of maintaining independent
  balance formulas.
- One Finance-owned **Settlement Position contract** is the only calculation
  seam for collectible amounts. It returns gross, applied discount, matched
  cash, applied credit, remaining collectible, and settlement state as distinct
  values. Controllers, queries, DNG, APIs, reports, and UI read models must not
  reproduce settlement arithmetic. A batch-optimized implementation is allowed
  for large lists only when contract/parity tests prove it returns the same
  positions as the canonical single-line reader.
- This canonical contract is Finance-internal. Shared cross-context contracts
  such as Financial Clearance or source-keyed obligation settlement remain
  narrow adapters over it and do not expose payable-line or application-ledger
  internals outside the Finance bounded context.
- Consumers select an explicit business scope, but the canonical contract owns
  target validation and aggregation. It returns one aggregate Settlement
  Position plus optional line breakdown; consumers never sum line positions
  themselves. A scope containing any invalid or ineligible target is invalid as
  a whole rather than silently dropping that target from the total.
- Audit, history, and timeline readers may inspect raw ledger entries as
  explanatory evidence. They still obtain every authoritative balance,
  collectible total, and settlement state from the Settlement Position contract
  and must not re-aggregate the evidence into a competing money position.
- Collection mutations fail closed. After a consumer cuts over to the canonical
  contract, a missing, invalid, or unreconciled Settlement Position blocks DNG
  or other collection execution and surfaces review evidence. It must never
  fall back silently to a legacy charge-minus-payment formula.
- The canonical reader preserves raw signed application sums and validates them
  before deriving a collectible amount. Negative net applications,
  over-application, reversal beyond the amount applied, or an unreconciled
  remaining position returns an invalid position with stable issue codes and
  evidence. It must not use `max(0, ...)` or `min(...)` to conceal ledger
  inconsistency from collection consumers.
- Invalid positions block payment and collection actions on both staff and
  student surfaces. Staff receive the separated money components, stable issue
  codes, evidence, and repair navigation; student-facing surfaces receive only
  a neutral review notice and no internal integrity details or untrusted
  collectible amount.
- A DNG or installment collection amount is capped by the canonical remaining
  collectible. An installment schedule is a collection plan, not an independent
  debt source; it cannot authorize collection above the Settlement Position.
- The normal DNG flow has no arbitrary amount override. Partial collection is
  expressed by an explicit Installment Plan that still reconciles to the
  Settlement Position. Any future collection exception requires its own audited
  workflow and cannot bypass line linkage with a raw amount field.
- Settlement mutations serialize at the Billing Account boundary. DNG
  reservation/push, payment application/reversal, discount or credit
  application/reversal, and installment reconciliation use the same Settlement
  Mutation Guard and verify that the position used for a decision is still
  current before committing it. Per-line locks alone are insufficient because
  one collection request may span multiple lines.
- Each Billing Account carries a monotonic Settlement Version. Every guarded
  mutation that changes settlement evidence or a collection plan increments it.
  Settlement Positions expose the version used to derive them; collection
  reservations persist it, and finalize/retry operations reject a reservation
  whose version no longer matches. Timestamps, amount-only comparison, and
  whole-ledger hashes are not concurrency tokens.
- External collection uses a local reservation protocol: under a short guarded
  transaction Finance derives the canonical position, stores a pending
  Collection Reservation with its payable-line/installment links and commits;
  it calls DNG outside the database transaction; then it reacquires the guard to
  finalize the reservation as pushed or failed. Pending reservations block
  conflicting settlement mutations. If the provider succeeds but local
  finalization fails, reconciliation repairs from the durable reservation and
  provider audit evidence; Finance never holds row locks across network I/O.
- A reservation whose provider call was attempted but has no confirmed outcome
  becomes an Unknown Collection Outcome and keeps its collection hold until
  reconciliation proves that the provider rejected, accepted, or cancelled it.
  Only a reservation with no provider attempt may be released after a lease
  timeout. Finance never blindly retries or releases an uncertain external
  collection merely because time elapsed.
- Batch collection is transport batching only. Each Billing Account and DNG item
  has an independent reservation, Settlement Version, stable provider item ID,
  payable-line/installment links, and outcome. Provider partial success is
  finalized item by item; one failed or unknown item neither rolls back nor
  relabels another payer's confirmed result.
- For one Billing Account, each DNG fee type has exactly one active unpaid slot.
  Two unpaid requests of the same type never coexist. Fee type remains the
  provider-facing collection slot key, while reservations preserve the exact
  obligation, payable-line, and installment targets behind that request.
- One active fee-type request aggregates every eligible, due target in its
  explicit collection scope and keeps the exact obligation/line/installment
  breakdown. Student payment access for an individual request sends only that
  selected fee type to DNG; pay-all access sends all active fee types. The
  per-request API must not expand a selected type into every pending type.
- Pay-all is valid only when every active fee-type position in its scope is valid
  and unheld. It fails closed as a whole when any type is under review and never
  silently omits that type while presenting the action as "all". Valid fee types
  remain independently payable through their per-type actions.
- Cancelling a DNG or other collection request terminates only the external
  collection attempt and releases its reservation/hold. It never voids the
  linked obligation, charge, payable line, installment plan, or source workflow.
  A lifecycle use case that truly cancels an obligation invokes a separate,
  explicitly authorized Finance cancellation workflow after collection state is
  resolved.
- An obligation/source cancellation resolves DNG by state: a local unattempted
  reservation is released; a pushed unpaid request must be confirmed cancelled;
  an unknown provider outcome blocks in review; a paid request is bridged into
  the Settlement Ledger and preserved as history before the payable is voided.
  If one aggregated request contains several obligations, removing one target
  requires confirmed cancellation of the aggregate and an explicit replacement
  for the remaining canonical position.
- The Settlement Mutation Guard is payer-scoped and short-lived, while a
  reservation's persistent collection hold is scoped to its linked payable lines
  and installments. Mutations unrelated to those targets may continue. A command
  that selects targets dynamically must explicitly exclude held targets or fail
  closed when it cannot establish a safe target; it never moves settlement
  evidence into or out of a held line implicitly.
- Generic payment auto-allocation and automatic discount or credit application
  exclude held payable targets. If no safe target remains, incoming cash stays
  **Còn dư** rather than being forced onto a held line. Only settlement evidence
  correlated to the reservation itself, such as its verified DNG webhook or
  reconciliation result, may settle the held target and release the hold.
- Voiding a paid obligation releases its payment applications from the voided
  payable lines and leaves the real cash as **Còn dư** by default. Reallocation,
  refund, and policy-authorized forfeiture/retention are separate, explicit,
  independently authorized workflows; a source-side no-refund acknowledgement
  cannot silently choose or execute one of them. Original DNG, Payment, and
  application/release entries remain settlement evidence.

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
- DNG convergence may land through several reviewable implementation slices,
  but production cutover is one hard gate. The canonical reader, mutation
  guard/version, reservation protocol, cancellation/replacement lifecycle, and
  active-request migration must all reconcile before the new money-moving path
  is enabled. Finance does not run a mixed old/new DNG calculation or lifecycle
  path in production; pre-cutover comparison is shadow-only.
- The production kill switch is fail-closed: it stops new collection
  reservations, pushes, replacements, and payment-access generation but never
  re-enables a legacy balance formula. Webhook capture, paid-cash bridging, and
  reconciliation continue because external money may already be in flight;
  unsafe provider requests are reviewed or cancelled explicitly before
  canonical collection resumes.
- Cutover also requires zero unresolved active DNG requests. Every pending or
  pushed request must map exactly to its obligation, payable lines, and relevant
  installments before a reservation is backfilled. Finance never infers those
  links from fee type or amount alone; unmapped requests remain exceptions until
  staff repairs the linkage or confirms provider cancellation.
- Cutover requires zero paid-but-unbridged DNG requests. Externally confirmed
  cash is bridged idempotently into a canonical Payment. Exact links allocate it
  to payable lines; without safe links Finance records the cash as **Còn dư**,
  marks review, and blocks recollection for the affected scope instead of
  ignoring the payment or auto-allocating by guess. A Settlement Position with
  unbridged paid evidence is invalid for collection.
