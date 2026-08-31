# Retake/Resit Business Rule Alignment

**Date**: 2026-08-31 19:15
**Severity**: Medium (money-path semantics change)
**Component**: Academic retake/resit ↔ Finance cancellation operation
**Status**: Resolved (3 commits, unpushed)

## What Happened

Executed `plans/260831-1213-retake-resit-business-rule-alignment/plan.md` end-to-end via /ak:cook (code mode, 4 phases in order). 3 commits:

- `000e404d1` feat(finance): two paid-cancellation outcomes (forfeit vs keep-for-later)
- `2b33157ce` feat(academic): retake/resit business-rule alignment
- `bdf8bb42b` docs: ADR-0052 + user guide 4 locales + plan status

## Key Decisions / Gotchas

1. **Retake fee single-source-of-truth**: `markFinanceObligationCreated($userId, float $retakeFee)` now writes `retake_fee` from `FinanceIntakeResult::amount` (red-team finding C). The old test `snapshots retake_fee from unit` encoded the exact bug and was replaced.
2. **Disposition keyed on `paid_void_reason` string** (`str_contains 'keep_for_later'`), not a new handoff column — cheapest change that flows through the existing outbox without migration. Fragile if someone renames the reason; the string literals are class constants on `CancelExamResitAttemptAction`.
3. **Forfeit = charge stays ACTIVE + obligation ACCEPTED** — the semantic inversion from the red-team finding A (old behavior voided paid charges). `reconcileLatePayment` had to be restructured: forfeit ops return early before bridging, keep-for-later voids during late-paid upgrade.
4. **`CourseRetakeRegistration::cancel()` now rejects `paid`** — legacy direct terminal cancel would bypass the Finance handoff. Paid must flow through `markFinancePendingCancellation`.
5. **Edit-tool footguns this session**: 3 file corruptions from mis-anchored `PUT >N` landing inside sibling blocks (ExamResit Index.vue twice, CompleteFinanceCancellationOperationAction once). Each caught by syntax probe + test ParseErrors. Lesson: for files with many adjacent insertions, batch into one edit or rewrite the region.

## Pre-existing Failures (verified on stashed baseline, NOT ours)

- `Finalization/FailureReasonFinalizationTest` (2)
- ~82 Finance failures (Audit/Defer/AutoAllocate/etc.) — environment/ordering
- 6 `tests/Feature/Architecture` failures incl. `SettlementBypassArchitectureTest`

## Follow-ups

- Unresolved question from code review: confirm Finance fee-monitor/invariant audits tolerate a forfeit's active-paid charge on a cancelled academic source (ADR-0052 §3 records the decision; invariant-check script not verified).
- Forfeit on partially-paid resit charge leaves outstanding balance collectible — intended per owner rule but undocumented in ADR; consider adding a sentence.
- `CourseRegistration::is_retake_paid` boolean cast returns true for DB value 'no' (observed in test); legacy string field, cleanup out of scope per plan.
