---
phase: 5
title: "Generation gate, docs & verification"
status: done
priority: P2
effort: "0.5d"
dependencies: [1, 2]
---

# Phase 5: Generation gate, docs & verification

## Overview

Close the timing hole a pending restoration proposal opens against tuition
generation, update the PRD, and run the whole-feature verification pass.

## Requirements

- Functional: batch-studio tuition generation skips a student whose carried
  adjustment has a `pending_approval` restoration proposal for a semester the
  charge targets; preview shows the skip reason (mirror of shipped
  "generate XOR reduce" invariant from plan 260807 — otherwise tuition is
  generated at the reduced level and an approval minutes later requires manual
  patching).
- Docs: PRD gains restoration UI + partial semantics + permission note.

## Architecture

Same seam as plan 260807's shipped gate: set-based reader + block flag inside
`GenerateBatchChargesAction::execute` (`Operations/GenerateBatchChargesAction.php:85-118`
— `PendingScholarshipAdjustmentReader::inFlightByStudent` at :86-87,
`$blockTuitionForScholarshipReview` at :106-109, stats key
`deferred_scholarship_review` at :97,115-117) and preview plumbing
(`PreviewMajorChargeGenerationQuery.php:123-124` + `eligibility_reason` string
at :217). New predicate: carried prior adjustment has a `pending_approval`
restoration proposal. Skip reasons are free string literals (no enum) — add
e.g. `'scholarship_restoration_pending'` beside `'scholarship_review_pending'`.

## Related Code Files

- Modify: `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php` (~:85-118) — second set-based predicate + stats key + block flag
- Modify: `app/Modules/Finance/Queries/Major/PreviewMajorChargeGenerationQuery.php` (~:123-124, :217) — same predicate, new `eligibility_reason` string
- Modify: `docs/features/academic/scholarship-adjustment-deduction.md` —
  sections: restoration web workflow, partial semantics table, permission
  reuse, generation gate, console command retirement
- Tests: batch generation skip + preview reason tests beside plan 260807's tests

## Implementation Steps

1. Add the pending-restoration predicate beside the existing in-flight-dossier
   reader (shared helper if both checks colocate naturally — DRY only if it
   falls out, don't force).
2. Preview reason string via `lang/vn`.
3. Tests: pending proposal ⇒ skipped with reason; approved/rejected/none ⇒
   generated; approved-partial ⇒ generated at restored level (ties Phase 1).
4. PRD update.
5. Whole-feature verification: run all new test files + touched Finance test
   files explicitly; `pint` on touched PHP; per-file eslint on touched Vue/TS.
   Known baseline failures (Finance 26, attendance CHECK-constraint, SSE retry)
   are pre-existing — don't chase.

## Todo

- [x] Generation + preview gate with reason
- [x] Gate tests green
- [x] PRD updated
- [x] Full verification pass recorded

## Success Criteria

- [x] Pending restoration proposal blocks that student's tuition generation with visible reason
- [x] PRD reflects shipped behavior (restoration UI, partial, permission, gate)
- [x] All new tests green; no new regressions vs known baseline

## Risk Assessment

- Gate too broad (blocking unrelated charge types): scope predicate to
  tuition generation only, exactly as the shipped `$canGenerateTuition` flag does.
