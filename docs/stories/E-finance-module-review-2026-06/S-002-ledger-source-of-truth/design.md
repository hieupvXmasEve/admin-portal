# Design

## Domain Model

Target source of truth:

- `finance_charges`
- `invoice_lines`
- `payments`
- `payment_applications`
- `invoice_discounts`
- `discount_allocations`

`student_invoices.*amount*` columns remain cache/snapshot fields, not the source
of truth for new calculations.

`dng_payment_requests.amount` is the amount pushed to the provider. It is not
the canonical outstanding debt amount after a payment has bridged into the
ledger.

## Application Flow

- Keep `SettlementService::deriveInvoiceSnapshot` as the canonical calculation
  or introduce a narrowly named replacement inside Finance.
- Update duplicate derivation paths to call the canonical calculation.
- Replace charge-centric fallback allocation paths that conflict with invoice
  line truth.
- Add `lockForUpdate` or an equivalent row-locked boundary around payment
  unapplied amount and invoice-line outstanding reads before writing
  `payment_applications`.
- Enforce operation-level idempotency through check-before-write under lock, or
  through a dedicated operation token if the implementation needs a persisted
  key. Do not add natural-key unique constraints to append-only ledger rows.
- Make invoice line `amount_snapshot` immutable for existing rows.
- Add defensive filters and regression tests for void/reversal signed-ledger
  paths.
- Rename or otherwise mark invoice snapshot columns as cache where the project
  accepts that migration; add a rebuild command or runbook for invoice snapshot
  cache.
- Clear cached `paid_at` when reversal makes an invoice no longer paid.
- Add a DNG-vs-ledger reconciliation proof so KPI code cannot double-count the
  same obligation from both rails.

## Interface Contract

Admin web props must keep snake_case and preserve existing page names/routes
unless intentionally changed in a later UI story.

## Data Model

No broad constraint migration in this story unless required for immutability,
cache naming, or operation idempotency. If a migration is required, it must be
additive and preceded by invariant proof.

## UI / Platform Impact

Finance pages may show corrected balances. UI changes should be minimal and
focused on reading canonical props.

## Observability

Record before/after invariant output and targeted tests showing the same
student no longer receives divergent balances across pages. Include a
concurrency test that would previously over-allocate under webhook + batch
allocation.

## Alternatives Considered

1. Patch each screen calculation separately.
   - Rejected because it preserves the multi-source failure mode.
2. Treat invoice cache columns as truth.
   - Rejected because the review confirms they can drift and must be rebuildable
     cache.
