# Credit reduction flows through a credit-application ledger, not negative charges

**Status:** Accepted
**Date:** 2026-07-09
**Last updated:** 2026-07-11
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

Cash and credit remain distinct in every derived settlement position and
staff/student presentation. Applied credit reduces **Còn phải thu** but never
increases **Đã thu**; a cache or compatibility projection must not relabel
credit as cash received. In particular, `cached_paid_amount` and any field whose
contract means "paid" contain matched cash only. If applied credit ever needs a
cache, it uses an explicitly separate credit field and remains rebuildable from
the credit-application ledger.

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
- Applied credit is derived directly from the indexed credit-application ledger
  by default; this decision does not introduce a credit cache column. A separate,
  rebuildable credit cache may be added only after measured read performance
  shows the ledger-derived path is insufficient.
- Applying or reversing credit reconciles any Installment Plan against the
  canonical remaining collectible. The FIN-09 reconciliation target includes
  applied credit, may redistribute only pending portions, and never rewrites a
  portion already awaiting collection or paid. If committed portions exceed the
  new collectible amount, the operation fails closed for staff review rather
  than rewriting a DNG request already sent.
- A credit reversal that increases collectible amount without enough pending
  installment capacity requires an explicit staff-confirmed collection plan,
  including its amount and due date. The reversal effect and new pending portion
  are committed atomically; without that plan the settlement effect remains
  blocked for review. Finance never invents an installment schedule or pushes a
  DNG request automatically.
- Credit approval and credit application are separate decisions. When an active
  unpaid DNG request would collect more than the post-credit Settlement Position,
  Finance records the approved entitlement but blocks its application to the
  payable line and surfaces staff review. Staff explicitly resolves or cancels
  the conflicting DNG request before applying the credit; any replacement DNG
  request is created later through the normal collection flow.
- Credit approved after a payable line is already cash-settled remains an
  approved, available entitlement; Finance does not silently rewrite historical
  payment applications or relabel cash as surplus. Applying that credit
  retroactively requires an explicit reallocation/refund workflow that releases
  cash, applies the credit, and records whether the released cash becomes
  **Còn dư** or is refunded.
- Student-facing summaries that summed negative charge rows
  (`total_credits`/`net_amount` in the student charges API) are reworked to
  read entitlements.
- Tests pinning "negative charges get invoice lines"
  (`CreditMemoPaymentTest`, `LedgerSourceOfTruthTest`) are rewritten in the
  waves that convert their fixtures.
