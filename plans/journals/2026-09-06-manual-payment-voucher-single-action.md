---
title: Manual payment voucher single action
date: 2026-09-06
summary: "Phase 3: record+allocate+internal voucher in one guarded action"
---

# Manual payment voucher single action

## What happened
Cooked Phase 3: one staff action records a manual Payment, allocates confirmed charge amounts, and issues finance_payment_vouchers (PaymentVoucher — not DNG Receipt). SettlementMutationGuard wraps money writes. Retake/resit syncers run after the guard with try/catch so Academic errors do not roll back cash.

allocatePayment still returns Collection. allocatePaymentWithReport adds skipped reasons: dng_held, no_active_line, capped, student_mismatch. Exhausted unapplied cash now records capped instead of dropping later charges.

Idempotency_key unique on payments. Voucher numbers PV-year-student-timestamp-random with unique retry. Print is Blade A4, legal line not VAT. Leftover enrolled cash is work type enrolled_unapplied_cash via ListEnrolledUnappliedCashQuery (Phase 8 displays).

Reviewer: preview drawer must unwrap VueUse ApiResponse; fixed. RecordPaymentDrawer is two visible steps (amount → allocate/confirm).

## Decision
No PDF lib. No sequential voucher numbers. No retain_forfeit on leftover for students still enrolled. Docs-site skipped: no page lists RecordPaymentDrawer in source.

## Next steps
Phase 4 unified money-item status. Commit Phase 3 pathspec only.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
