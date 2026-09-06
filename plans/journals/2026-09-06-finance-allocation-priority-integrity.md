---
title: Finance allocation priority integrity
date: 2026-09-06
summary: "Phase 1: 8-type allocation policy, validation, campus scope, server-driven FE options"
---

# Finance allocation priority integrity

## What happened
Cooked Phase 1 of plans/260904-1114-finance-flow-redesign (allocation-priority integrity). Single source is ObligationTypeRegistry::allocationPriorityOrder(): tuition_term → egc_level_fee → bhyt → exam_resit_fee → retake_fee → admission_fee → manual_fee → adjustment.

Write paths (ApplySettlementRequest + PaymentController preview/allocate) reject non-permutations with AllocationPriorityOrder (422, no payment_applications). Shared sort keeps ?? fallbackRank so Student 360 reads do not 500 on unknown/legacy types.

PreviewManualAllocationQuery now uses SettlementService::sortLinesByAllocationPriority without getLineOutstandingAmount, because routing through getOutstandingLinesForStudent threw missing_currency and broke fail-closed preview (200 + issues).

runForStudents asserts campus via StudentReferenceReader + view_finance_all_campus; foreign student_ids → AuthorizationException 403.

Settlement worklist serves allocation_priority_options. FE dropped hardcoded 4-type lists. Empty options disable apply.

## Decision
Do not fail-closed inside the shared comparator. Campus guard lives in the action. No docs-site this phase (no user-facing copy change).

## Next steps
Phase 3: single-action manual payment + internal receipt. Do not cook remaining phases until asked. Commit Phase 1 pathspec only; leave unrelated dirty Academic/plan/test files out.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
