---
id: ADR-0054
title: "Manual receipt in one action, internal phiếu thu, surplus lifecycle"
status: accepted
owner: Finance Team
last_verified: 2026-09-07
scope: architecture-decision
portal_impact: none
---

# Manual receipt and surplus lifecycle

**Context.** Cash/transfer collection was split across record, allocate, and
print. Leftover cash had no owner once a student left school. Owner sessions
2026-09-04 and 2026-09-06 required one staff action for cash collection and a
derived leftover-cash queue, without building a refund product.

**Decisions.**

1. **Manual collection is one action.** Recording cash or transfer allocates
   by the shared fee-type priority, prints an internal phiếu thu, and syncs
   Academic retake/resit state in the same use case. The phiếu thu number is
   random-with-retry (same pattern as `invoice_number`); there is no
   gapless campus sequence.

2. **The phiếu thu is an internal receipt.** It is not a VAT invoice and not
   a GL posting. Students pay by QR; accountants still record cash/transfer
   in Swinx.

3. **No refund while the student is still enrolled.** Leftover cash stays as
   student unapplied balance (ADR-0022). The existing refund write path and
   refund UI are closed. Report reads that mention `refund` remain.

4. **A student who has left school with leftover cash enters a derived
   queue** (`ListUnresolvedSurplusQuery`: graduated, dropout,
   dropout_transfer). Finance Cockpit surfaces that queue to staff who can
   open Student 360. `retain_forfeit` requires a second person
   (`approved_by != created_by_user_id`) with
   `forfeit_finance_payment_surplus`. Reallocation still uses
   `allocate_finance_payment`.

**Consequences.** Settlement Position remains the only money seam. Cockpit
does not store queue state. A production refund-disposition row, if any, is
historical evidence and is not a live write path.

**Related.** ADR-0022, ADR-0028, ADR-0030;
plan `260904-1114-finance-flow-redesign` phases 3 and 7.
