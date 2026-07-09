# Credit reduction flows through a credit-application ledger, not negative charges

**Status:** Accepted
**Date:** 2026-07-09
**Owner:** Finance

## Context

Legacy credits are negative `finance_charges` rows. A code/data audit showed the
credit types are not uniform: `defer_credit`/`egc_exempt_credit` reductions are
carried **solely** by their negative invoice line via a netting fallback in
`SettlementService::deriveInvoiceSnapshot`, while voucher and some scholarship
reductions may already have their real carrier in
`invoice_discounts`/`discount_allocations`. With `FinanceCreditEntitlement`
becoming the credit source of truth, the question was what carries a credit's
effect on settlement once negative charge rows stop being written.

## Decision

New credit flows **never** write negative `finance_charges` rows. Applying a
`FinanceCreditEntitlement` writes rows to a dedicated **credit-application
ledger** (entitlement → invoice line, amount, applied timestamp). Settlement
derivation gains a fourth source:

```
outstanding = invoice line − payment applications − discount allocations − credit applications
```

Discounts keep their existing carrier (`invoice_discounts`/`discount_allocations`);
credits get their own, because the glossary distinguishes them deliberately — a
discount reduces the fee, a credit reduces the outstanding when applied
(payment-like).

Legacy negative rows are converted per migration wave, **split by carrier so a
reduction is never counted twice**:

- `defer_credit`/`egc_exempt_credit` rows whose reduction is carried only by the
  negative line become `FinanceCreditEntitlement` + credit applications.
- `voucher_credit` and fee-specific `scholarship_credit` rows whose reduction
  already lives in `invoice_discounts`/`discount_allocations` become
  `FinanceDiscountEntitlement` linked to those existing discount rows and get
  **no** credit applications.
- Grant-like `scholarship_credit` rows with no discount-allocation carrier
  become `FinanceCreditEntitlement` + credit applications.

In every case the negative line is voided in the same transaction, net unchanged
under the backfill reconciliation hard gate, with tests proving no reduction
appears in both discount allocations and credit applications. The
`deriveInvoiceSnapshot` negative-line fallback survives only as a legacy
backstop and is deleted at the hardening wave once zero active negative lines
remain.

## Considered options

- **Keep materializing negative charges from entitlements** — rejected by
  product decision: accept the comprehensive fix, no dual mechanism.
- **Reuse `invoice_discounts` with a `kind=credit` flag** — rejected: merges
  two concepts the glossary separates, and every discount report would need to
  learn the flag.

## Consequences

- Every settlement/outstanding reader must include credit applications; the
  reader ships in the same wave as the first credit entitlement.
- Student-facing summaries that summed negative charge rows
  (`total_credits`/`net_amount` in the student charges API) are reworked to
  read entitlements.
- Tests pinning "negative charges get invoice lines"
  (`CreditMemoPaymentTest`, `LedgerSourceOfTruthTest`) are rewritten in the
  waves that convert their fixtures.
