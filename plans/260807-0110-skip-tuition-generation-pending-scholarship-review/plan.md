---
title: "Skip Tuition Generation Pending Scholarship Review"
description: "Batch-studio skips tuition_term generation for students with an in-flight scholarship adjustment dossier; staff see the reason in preview, students get a realtime+email deferral notice; once fee is generated the reduction can no longer be opened."
status: done
priority: P1
effort: "3-4d"
tags: [finance, academic, notification, scholarship, batch-studio, boundary]
created: 2026-08-07
blockedBy: []
blocks: []
---

# Skip Tuition Generation Pending Scholarship Review

## Overview

Follow-up to the shipped **Scholarship Adjustment Deduction** feature
([plans/260731-2347-scholarship-adjustment-deduction](../260731-2347-scholarship-adjustment-deduction/plan.md), status `done`;
PRD [docs/features/academic/scholarship-adjustment-deduction.md](../../docs/features/academic/scholarship-adjustment-deduction.md)).

Today, when Academic staff run a scholarship-adjustment dossier (fail courses →
interview → decision → approve) the money side only reacts **after approval**.
If Finance generates tuition at `/finance/batch-studio` **while a dossier is
still in-flight**, the invoice is created with the **full original scholarship**,
and the later approval either patches an open invoice or drops into
`finance_review_required` (manual refund/back-charge). Confirmed by reading
`GenerateBatchChargesAction` (only skip is `DeferChargeResolver::isSemesterEnrollmentDeferred`
— no scholarship-dossier gate) and `ScholarshipAdjustmentCandidateQuery` (no
"already-charged" predicate).

This plan enforces a **"generate XOR reduce" timing invariant** on both sides:

1. **Generate side** — batch-studio skips generating `tuition_term` charges for
   any student with an in-flight dossier for that target semester; staff see the
   reason in the preview before executing.
2. **Candidate side** — scholarship-adjustment candidate identification excludes
   students who already have a generated `tuition_term` charge for the target
   semester, so a reduction can never be opened after the fee exists.
3. **Student notice** — each generate-side skip emits a realtime + email notice
   ("tuition deferred pending scholarship review"), mirroring the shipped
   confirmation-email pattern (commit `41794129`).

The existing `ScholarshipAdjustmentTimingGuard` stays as the post-approval
safety net; nothing in the current apply-path is deleted.

## Goals

| # | Goal | Priority |
|---|------|----------|
| 1 | Two module-boundary-safe Shared contracts + `IN_FLIGHT_STATUSES` constant, set-based (no N+1) | P1 |
| 2 | Batch-studio skips `tuition_term` for in-flight-dossier students; preview shows `scholarship_review_pending` | P1 |
| 3 | Candidate identification excludes students already charged `tuition_term` for the target semester | P1 |
| 4 | Realtime + email deferral notice with all template fields; idempotent (no batch re-run spam) | P1 |
| 5 | Arch/boundary tests + full targeted suite green | P1 |

## Non-Goals

- No recovery/backfill flow for the rare race (user decision: "đã tạo phí thì đã chặn không cho giảm").
- No change to scholarship money-math (`ScholarshipDiscountResolver` untouched).
- No new Finance staff management screen (separate concern from the earlier brainstorm).
- No change to restoration (Phase 5 of the parent feature).
- Do NOT delete `ScholarshipAdjustmentTimingGuard` — it remains the safety net.

## Timing invariant (authoritative)

```
in-flight dossier exists for (student, target semester)  ─┐
                                                          ├─► mutually exclusive by time
tuition_term charge exists for (student, target semester) ┘
```

- Generate reads the dossier reader → if in-flight, skip `tuition_term` (only).
- Candidate reads the tuition-charge reader → if already charged, exclude.
- Both readers are **set-based** (accept a student-id list, return a filtered
  set / map) to stay O(1) queries per batch run.

### In-flight statuses (Decision 1)

`ScholarshipAdjustmentDossier::IN_FLIGHT_STATUSES` =
`identified, interview_scheduled, interviewed, awaiting_student_confirmation,
ready_for_decision, student_disputed, student_no_show, confirmation_overdue`
(money not yet finalized → block generation).

Allow generate (NOT in-flight) = `approved, applied, no_adjustment, cancelled,
closed, finance_review_required, not_applicable`.

### Skip scope (Decision 2)

Only `FinanceCharge::TYPE_TUITION_TERM`. EGC/lab/resit and every other charge
type generate normally.

## Phases

| # | Phase | Status | Depends |
|---|-------|--------|---------|
| 1 | [Shared contracts + IN_FLIGHT_STATUSES constant + implementations](./phase-01-start.md) | Done | — |
| 2 | [Generate-side skip + staff preview reason](./phase-02-generate-side-skip-staff-preview-reason.md) | Done | 1 |
| 3 | [Candidate-side exclusion when tuition already generated](./phase-03-candidate-side-exclusion-when-tuition-already-generated.md) | Done | 1 |
| 4 | [Student notice: realtime + email deferral notification](./phase-04-student-notice-realtime-email-deferral-notification.md) | Done | 2 |
| 5 | [Arch tests, boundary proofs, and verification](./phase-05-arch-tests-boundary-proofs-and-verification.md) | Done | 1,2,3,4 |

Phases 2 and 3 are parallelizable once Phase 1 lands (different owners: Finance vs Academic).

## Success Criteria

- [x] In-flight dossier for target semester → batch-studio generates NO `tuition_term` charge for that student; other charge types unaffected.
- [x] Batch-studio preview lists the student as ineligible with reason `scholarship_review_pending` BEFORE execute.
- [x] Student already has a `tuition_term` charge for target semester → not surfaced as a scholarship-adjustment candidate.
- [x] Each skip sends realtime + email; re-running the batch does not re-notify (dedupe per student+semester+dossier minutes_version).
- [x] `Finance` still imports zero `Academic` classes; `Academic` reads Finance only via Shared contract; boundary arch tests green.
- [x] Targeted Finance + Academic + Notification test dirs green — 51 feature tests across every touched file, plus full `tests/Feature/Notification` (98) and `tests/Feature/Architecture` boundary/placement suites, all pass. Full `tests/Feature/Finance` (whole dir) has ~85 pre-existing failures unrelated to this change (`settlement_position.missing_currency`, a stale `egc_blocks.finance_charge_id` column dropped by an earlier migration, unrelated count-mismatch tests) — confirmed via `code-reviewer` subagent to touch zero files from this diff and reproduce in isolation on unmodified code.

## Implementation Note (discovered mid-build)

The plan named `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php` as the execute-side file to gate. That class is only used by `FixBillingExceptionAction` (a billing-repair tool) — the actual live batch-studio "major" commit path is `BatchStudioController::commitCharges` → `GenerateMajorChargesAction`, a separate, simpler action with no scholarship-adjustment integration. Both actions were gated so the invariant holds on every path that can materialize a `tuition_term` charge; confirmed via code review that no third path exists (only `SubmitTuitionTermDebitAction` creates `tuition_term`, called solely by these two actions).

A code-review pass also caught and fixed two correctness bugs before merge: (1) `AddCandidateManuallyAction` could still open a dossier for an already-charged student (manual-add path bypassed the Phase 3 exclusion) — now blocked with the same reader; (2) the in-flight gate in both generate-side actions was checked before confirming tuition was actually due this semester, which would have sent a false "your tuition is on hold" notice to a student whose tuition was never going to be generated anyway — reordered so the gate (and the notify list) only fires for students who were otherwise eligible for tuition this semester.

## Open Questions

- None (contract locked in brainstorm 2026-08-07; the one remaining confirm — "candidate exclusion guard is in scope even though recovery is not" — was accepted by the user).
- Should manual add (`AddCandidateManuallyAction`) be allowed to override the already-charged exclusion with the written exception reason as justification, instead of a hard block? Currently hard-blocked (surfaced by code review; no product decision exists yet).

<!-- slug: skip-tuition-generation-pending-scholarship-review -->
