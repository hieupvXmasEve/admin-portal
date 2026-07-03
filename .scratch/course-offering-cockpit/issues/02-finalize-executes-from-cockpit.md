# Finalize executes from the cockpit

Status: ready-for-human

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## What to build

Wire the cockpit's Finalize button (added in the operational-state slice) to actually complete the course offering. Clicking Finalize (permission-gated by `complete_course_offering`, only enabled when no readiness blockers remain) calls `MarkCourseOfferingCompletedAction`.

Extend `MarkCourseOfferingCompletedAction` to enforce the Canvas completion rule as a real backend block, not just a displayed blocker: reject finalization when a `CanvasCourseMapping` with `sync_status = mapped` exists and the offering is not synced. This is a deliberate business-rule change — previously finalization silently fell back to manually entered grades when Canvas wasn't synced. `pending`/`ignored` mappings and unmapped offerings are unaffected. The rule applies going forward only; already-completed offerings are untouched (no backfill).

After a successful Finalize, the frontend refreshes `operational_state` via an Inertia partial reload of only that prop (no full page reload, no parallel endpoint). The same partial-reload mechanism should work for any other mutating action in the cockpit going forward.

## Acceptance criteria

- [ ] Finalize button click calls `MarkCourseOfferingCompletedAction` for the current offering.
- [ ] Route/controller enforces `complete_course_offering` permission; unauthorized requests return 403.
- [ ] `MarkCourseOfferingCompletedAction` throws/blocks when a Canvas mapping with `sync_status = mapped` exists and the offering is not synced; `pending`, `ignored`, and unmapped offerings finalize normally (all else being ready).
- [ ] Existing attendance-based completion rules (unmarked sessions, auto-system-only sessions) continue to be enforced unchanged.
- [ ] On success, the frontend issues an Inertia partial reload that refreshes `operational_state` (and any other stale cockpit data) without a full page navigation.
- [ ] Already-completed offerings are not retroactively affected by the new Canvas rule (no backfill/reprocessing).
- [ ] Pest HTTP feature tests assert: real backend blocking on attendance blockers, real backend blocking on the Canvas blocker, 403 for missing `complete_course_offering`, and the success path (offering transitions to completed, response reflects refreshed `operational_state`).

## Blocked by

- Operational state read model + cockpit displays lifecycle, blockers, and gated Finalize button (display only)

## Comments

2026-07-03 (agent): Implemented. Web route `course-offerings/{courseOffering}/finalize` (can:complete_course_offering) → `FinalizeCourseOfferingController` → `MarkCourseOfferingCompletedAction`; Canvas mapped-but-unsynced block added to the action, gated on `course_status !== 'completed'` so recalculation of completed offerings is unaffected. Frontend: confirm dialog then `useCockpitAction` composable posts and partial-reloads `operational_state` + `courseOffering` (reusable for future cockpit mutations). 12 Pest feature tests in `tests/Feature/CourseOffering/CourseOfferingFinalizeTest.php`. Manual check remaining: verify in the browser that the POST's partial-reload headers survive the 302 follow (failure mode is a benign full-prop refresh, still no page navigation).
