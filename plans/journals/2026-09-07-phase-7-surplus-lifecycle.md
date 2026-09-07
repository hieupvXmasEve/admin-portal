---
title: Phase 7 surplus lifecycle
date: 2026-09-07
summary: Blocked refund writes with a policy message for every caller; leaver unapplied-cash queue; two-person retain_forfeit.
---

# Phase 7 surplus lifecycle (Vòng đời số dư)

**Date**: 2026-09-07
**Severity**: Medium
**Component**: Finance payment surplus / Student360 / ListUnresolvedSurplusQuery
**Status**: Resolved (cook complete; Inbox + ADR deferred to Phase 8)

## What Happened

Cooked `plans/260904-1114-finance-flow-redesign` Phase 7. Owner already decided: no refunds. Enrolled students keep unapplied cash as balance; graduates/dropouts with leftover cash go to a derived queue for a second person to decide. Today the `refund` write path was still open from Student360.

Blocked writes at `DisposePaymentSurplusRequest::prepareForValidation` **and** the Action so every caller gets the same policy response: `"chính sách hiện tại không hoàn tiền"` — not a naked 403. `authorize()` runs before `rules()`; dropping `refund` from `Rule::in` alone would have given staff without `refund_finance_payment` a blank 403 and staff with the perm a 422. That split was the trap.

Kept `TYPE_REFUND`, `refund_finance_payment`, `GetRevenueByPeriodQuery` refund reads, and the `UnappliedCashReader` formula. Removed `can_refund_surplus` and the Hoàn tiền button from Student360. `Payment::STATUS_REFUNDED` stays a dead constant — nobody in `app/` assigns it; out of scope.

## The Brutal Truth

We almost treated "no refunds" as delete-the-enum. That would have been stupid. Dev has **0** refund dispositions (0 of any type). Production was **not** queried this session. If prod ever wrote a refund row, wiping `TYPE_REFUND` would have made history unreadable. Keep the read path, kill the write path. The previous plan's "no approval, need a reason column" was already wrong — `reason`/`policy_code`/`approved_by`/`evidence`/`audit_signature` existed since `2026_07_11_230000`. Phantom tests. The real hole was two-person approval.

## Technical Details

- Message: `PaymentSurplusDisposition::REFUND_BLOCKED_MESSAGE` = `chính sách hiện tại không hoàn tiền`
- Queue: `ListUnresolvedSurplusQuery` = `unapplied_cash > 0` ∩ enrollment `graduated|dropout|dropout_transfer` (not deferred). Campus null **fail-closed** unless `view_finance_all_campus`. Batch `ProgramEnrollmentReader` — do not copy `SendDueItemRemindersAction`'s per-student loop.
- `retain_forfeit`: `approved_by !=` operator; only if the student is already in the leaver queue. Operator id goes in evidence JSON (`created_by_user_id`); **no new column**.
- Tests green: `PaymentSurplusDispositionTest` 7, `ListUnresolvedSurplusQueryTest` 4, `GetRevenueByPeriodQueryTest` 5, `Student360OverviewTest` 5. Pint dirty pass. ESLint/Prettier on changed FE files pass.
- Reviewer 8/10, 0 critical. Flagged `EloquentProgramEnrollmentReader` dirty file is **PRE-EXISTING**, not this phase.
- Docs-site: `Student360/Show.vue` is not on the finance-office source list. ADR/Inbox are Phase 8.

## Decision

Reject refund at prepareForValidation + Action (same message, all callers). Keep historical refund **reads**. Derived leaver queue, no new tables. Two-person forfeit without a migration. Do not hide `refund_finance_payment` from config — roles still hold it.

Rejected: 403-only block; deleting enum/permission; inventing a status column on `payments`; including deferred in the leaver set.

## Lessons Learned

Campus-null worklists in this codebase fail three different ways. Copy the wrong one and campus A sees campus B surplus. Fail closed. Also: read the schema before writing a red test for a column that already exists.

## Next Steps

Phase 8: Finance Inbox card for this queue + ADR for the no-refund / two-person forfeit policy. Someone with prod access should still count `payment_surplus_dispositions.type='refund'` before we pretend history is empty.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.

