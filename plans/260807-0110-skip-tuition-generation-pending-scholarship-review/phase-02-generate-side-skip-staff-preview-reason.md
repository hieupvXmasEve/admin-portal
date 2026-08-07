---
phase: 2
title: "Generate-side skip + staff preview reason"
status: done
priority: P1
effort: "1d"
dependencies: [1]
---

# Phase 2: Generate-side skip + staff preview reason

## Overview

Wire Reader A into both the batch-studio **preview** (so staff see the skip
before executing) and the batch-studio **execute** (so no `tuition_term` charge
is generated for an in-flight-dossier student). Only `tuition_term` is skipped.

## Requirements

- Functional:
  - Preview lists an in-flight-dossier student as ineligible with reason `scholarship_review_pending`.
  - Execute generates no `tuition_term` charge for that student; EGC/other types still generate.
  - Skip is counted in the run stats (`skipped_count`) and carries the reason.
- Non-functional:
  - Reader A queried ONCE per run (set-based), not per student.
  - Finance imports only the Shared contract, never an Academic class.

## Architecture

**Preview side** — single source is
`app/Modules/Finance/Queries/Major/PreviewMajorChargeGenerationQuery.php`
(used by `AssembleBatchChargePreviewQuery` → `BatchStudioPreviewController::previewCharges`,
and also by `ListFeeMonitorQuery` — bonus: Fee Monitor shows the reason too).
It already emits `eligibility_status` / `eligibility_reason` (e.g.
`already_charged`, `deferred`, `zero_amount_term`). Add a new terminal reason
BEFORE the eligible branch:

- Preload `inFlightByStudent($studentIds, $semesterId)` once for the previewed set.
- Per student: if in-flight → row `eligibility_status = 'ineligible'`, `eligibility_reason = 'scholarship_review_pending'`; skip the scholarship/net-amount math.
- Order it AFTER `already_charged` (a student already charged is not in-flight by the invariant, but keep `already_charged` first so a stale overlap still reads as charged).

**Execute side** —
`app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php`.
The `$students` collection is already materialized before the loop. Preload the
in-flight map once from that collection's ids + `$semesterId`, then in the loop
gate the tuition branch:

```php
// after $students = $students->filter(...)->values();
$inFlight = app(PendingScholarshipAdjustmentReader::class)
    ->inFlightByStudent($students->pluck('id')->all(), $semesterId);
// inside foreach, alongside $canGenerateTuition:
$blockTuitionForScholarshipReview = $inFlight[$student->id] ?? false;
$canGenerateTuition = $canGenerateTuition && ! $blockTuitionForScholarshipReview;
```

- Gate ONLY the `tuition_term` path — leave `$canGenerateEgc` untouched.
- When blocked and no other charge type is generated for the student, bump
  `skipped_count` and record the student in a `deferred_scholarship_review` list
  in the returned stats (Phase 4 reads this to emit notices).
- The post-commit `pending_apply` adjustment sweep (L396-409) is unaffected: an
  in-flight dossier has no `ScholarshipSemesterAdjustment` row yet, so nothing
  to transition.

## Related Code Files

- Modify: `app/Modules/Finance/Queries/Major/PreviewMajorChargeGenerationQuery.php` (new `scholarship_review_pending` reason)
- Modify: `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php` (preload map + gate tuition branch + stats)
- Possibly Modify: `app/Modules/Finance/Queries/Batch/AssembleBatchChargePreviewQuery.php` (only if it filters/relabels reasons — verify it passes rows through)
- Reference (no change): `app/Modules/Finance/Http/Api/Admin/BatchStudioPreviewController.php`

## Implementation Steps

1. Preview: preload map, add the `scholarship_review_pending` ineligible branch; confirm the client `toClientArray()` / summary buckets it under ineligible.
2. Verify `AssembleBatchChargePreviewQuery` forwards the new reason unchanged (read it; adjust only if it whitelists reasons).
3. Execute: preload map from `$students`, gate `$canGenerateTuition`, extend stats with a `deferred_scholarship_review` student list.
4. Confirm EGC-only and mixed runs still generate the non-tuition charge for a blocked student.
5. Tests: feature test on execute (in-flight → no tuition charge, EGC still made); query test on preview (reason surfaces); assert single reader query.

## Success Criteria

- [x] Preview: in-flight student → ineligible, reason `scholarship_review_pending`, no scholarship math run.
- [x] Execute: in-flight student → zero `tuition_term` charge; EGC/other types unaffected; `skipped_count` incremented.
- [x] Reader A hit exactly once per preview and once per execute run.
- [x] Non-in-flight students generate tuition exactly as before (regression).

## Risk Assessment

- **Risk:** `AssembleBatchChargePreviewQuery` or the client whitelists known reasons → new reason dropped. **Mitigation:** read it in step 2; add the reason to any enum/label map (FE label too).
- **Risk:** a student blocked for tuition but eligible for EGC gets double-handled. **Mitigation:** gate is scoped to the tuition branch only; mixed-run test covers it.
- **Risk:** stats shape change breaks existing batch-result consumers. **Mitigation:** additive key only; do not rename existing stats keys.
