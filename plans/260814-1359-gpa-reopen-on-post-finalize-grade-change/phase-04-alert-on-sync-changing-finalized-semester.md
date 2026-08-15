---
phase: 4
title: "Alert when automated sync changes a finalized semester"
status: todo
priority: P1
effort: "4h"
dependencies: []
---

# Phase 4: Alert when automated sync changes a finalized semester

> **Scope reversed 2026-08-14 on evidence.** This phase was originally "freeze
> finalized semesters from automated sync". Investigation showed the nightly sync
> is delivering **correct** grades over placeholder values, so freezing would have
> permanently locked in the wrong numbers. The phase now makes the change
> *attributable and visible* instead of blocking it. See "Why not freeze" below.

## Overview

The user's original complaint was not that grades change — it is that they change **without anyone knowing**. That is precisely what the evidence shows: `academic-records:sync` runs nightly at 03:00 (`routes/console.php:49-53`) and writes grade changes into already-finalized semesters with `causer_id = NULL`, leaving no attributable actor in the activity log. This phase closes the awareness gap: the sync keeps delivering data, but a change inside a finalized semester becomes an identifiable, reportable event.

## Why not freeze

Traced the five worst divergences through `activity_log`. Every one follows the same shape:

| Student / sem | At finalize (2026-05-12) | Nightly sync 05-13 03:0x (causer NULL) | Human 05-21 14:56 (causer 220) |
|---|---|---|---|
| 570 / 1 | 86.18 | → 47.40 | — |
| 519 / 2 | 100.00 | → 79.75 | → 47.85 |
| 622 / 2 | 100.00 | → 74.12 | → 53.68 |
| 515 / 2 | 81.25 | → 79.00 | → 47.40 |
| 561 / 2 | 100.00 | → 76.00 | → 66.40 |

A further batch at 2026-06-02 15:20:18 (12 rows, causer NULL) flipped `is_passed` true→false on FALL2025 records, which explains the 11-12 `*_credit_points_earned` divergences.

Read plainly: **GPA was finalized before grading was complete** — many records still held placeholder `100.00` values — and the sync then brought the real, lower grades. Freezing finalized semesters would have preserved the placeholders as the official record forever. The defect is therefore premature finalize plus no way to re-finalize (Phase 1), not the sync doing its job.

**Confirmed against Canvas (2026-08-15):** for student AUS10687 (id 570), unit AU008, Canvas holds **47.4** — the value the nightly sync wrote, not the `86.18` frozen into the GPA snapshot. The sync is delivering correct data. This is the evidence the freeze option was rejected on; do not revive freezing without new evidence that contradicts it.

## Requirements

- Functional: when the scheduled sync changes a GPA-relevant field (`final_percentage`, `credit_points`, `credit_points_earned`, `is_passed`, `excluded_from_gpa`, `grade_status`) on a record whose semester already has finalized `gpa_calculations`, that change is recorded as a distinct, queryable event naming the semester and the student.
- Functional: unattended runs record their origin in the activity entry's **properties** (`source: scheduled-sync`, the command name, the run timestamp). `causer_id` stays NULL — no synthetic user record is created (decision below).
- Functional: the sync command's summary reports how many records it changed inside finalized semesters and which semesters now need re-finalizing.
- Non-goals: no blocking of the sync, no change to what values it writes, no notification channel (email/Slack) — the activity log plus command output plus the Phase 2 badge are the awareness surface. Add a channel only if operators ask after using this.

## Architecture

Two small, independent changes:

1. **Attribution via properties, not a synthetic user.** The sync currently produces `causer_id = NULL` entries, which is why this incident was untraceable. Verified during validation: the repo has **no** existing system-actor convention — there is no `config/activitylog.php`, no console command sets `causedBy`, and no system/bot `User` record exists (the only `causedBy` usage found is `BulkUpdateCourseOfferingSessionsAction.php:107`, `causedBy(Auth::user())`). Decision: record origin in the activity entry's `properties` (`source`, command name, run timestamp) and leave `causer_id` NULL, rather than inventing a fake `User` row to satisfy the causer relation. "Who" is answered by `source`, and the `users` table stays free of non-people.
2. **Finalized-semester flagging.** At the point the sync persists a record change, check whether that record's semester has finalized GPA rows and, if so, emit a distinct log event and increment a counter surfaced in the command summary.

The check belongs where the sync writes, not in `CanvasGradeSyncService` broadly — that service is shared with the human Recalculate path, which needs no alerting because the operator is already present and acting deliberately.

Ruled out during investigation: `attendance:sync-to-academic-records` (every 2 hours) writes only attendance counts and `attendance_percentage`, never `final_percentage` (`SyncAttendanceToAcademicRecords.php:187-193`), so it is not a divergence source and is left untouched. `GenerateAcademicRecordsCommand` and `SyncCreditPointsToAcademicRecords` are manual, not scheduled.

## Related Code Files

- Modify: `app/Console/Commands/SyncAcademicRecordsCommand.php` (origin properties for scheduled runs, finalized-semester counter in summary)
- Modify: `app/Modules/Academic/Delivery/Support/CanvasGradeSyncService.php` (emit the distinct event on a finalized-semester change — only on the scheduled path, not the human Recalculate path)
- Read-only reference: `routes/console.php:49-53`, `app/Console/Commands/SyncAttendanceToAcademicRecords.php:187-193` (confirmed not a source), `app/Models/AuditableModel.php` (existing activity-log wiring)
- Create test: `tests/Feature/Academic/Gpa/FinalizedSemesterSyncAlertTest.php`
- Migration/route/frontend: none.

## Implementation Steps

1. Failing tests first: a scheduled sync changing a record in a finalized semester emits the distinct event whose properties carry `source` + command name, and increments the reported counter; the same change in a non-finalized semester emits no such event; the human Recalculate path is unaffected.
2. Add the origin properties on the scheduled path.
3. Add the finalized-semester detection + event + counter.
4. Surface the counter and the affected semester list in the command's summary output.
5. Run: `./scripts/dev.sh artisan test tests/Feature/Academic/Gpa`

## Todo

- [ ] Tests red then green
- [ ] A dev sync run reports a non-zero finalized-semester change count with attributable actors
- [ ] Human Recalculate path unchanged

## Success Criteria

- [ ] Every grade change inside a finalized semester names its origin in the activity entry and is discoverable without diffing tables by hand
- [ ] The sync still delivers corrected grades into finalized semesters (no freeze)
- [ ] Operators learn which semesters need re-finalizing from the sync output, not by opening each finalize page

## Risk Assessment

- Alert volume could be high on the first runs given 73 already-diverged pairs; the counter is per-run so it will settle once the correction campaign completes. Do not add a notification channel before observing real volume.
- Adding properties keys changes the activity-entry payload going forward; harmless for consumers that read known keys, but confirm nothing asserts an exact properties shape. `causer_id` semantics are unchanged (still NULL for system writes), which is why this option was preferred over introducing a synthetic user.
- This phase does not prevent premature finalize, by decision: finalizing early is a legitimate user action and will not be blocked. The compensating control is that it is now correctable (Phase 1) and visible (Phase 2).
