---
title: Unified money item status
date: 2026-09-06
summary: "Phase 4: derived money_item_status on presenter with DNG/due context"
---

# Unified money item status

## What happened
Cooked Phase 4: derived money_item_status on SettlementPositionWorklistPresenter (no new DB column, no separate resolver class). Codes: reviewing, adjusted, completed, processing, overdue, awaiting_payment. Overdue requires a due date; missing due date is never overdue. processing only when DNG reservation targets that item; holding beats overdue. hide_amounts + label_student for reviewing.

Review 5/10: student invoice API and staff invoice show originally called summarize() without DNG/due context. Fixed by injecting DngLineHoldingIndex into GetStudentFinancePresentationQuery and BillingInvoiceController.

Invoices/Show.vue reads money_item_status from server. settled_by_cash gone from resources/js/pages. Installment and due-calendar maps left (different domains). docs/api/student/finance.md updated. docs-site skipped because Invoices/Index.vue was not changed.

## Decision
Do not cache status. Phase 5 still owns making invoice due_date the staff-chosen date. Phase 9 owns Nuxt labels.

## Next steps
Phase 5 single due date. Commit Phase 4 pathspec only.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
