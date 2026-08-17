# Student Placement Worklist Shipped, HIGH Bug Caught by Review Before It Bit Prod

**Date**: 2026-08-17 12:36
**Severity**: Medium
**Component**: Academic/Progression module (backend + frontend), docs-site
**Status**: Resolved (uncommitted on dev, branch dev)

## What Happened

Executed all 3 phases of `plans/260817-1123-student-placement-worklist/` in one cook run: action-log gap fix, worklist backend, worklist UI. Backend query joins `students` to their primary `program_enrollments` to list students still sitting in `pending` status so staff can classify them. Post-implementation code review caught a HIGH-severity join bug before merge: the worklist join used `is_primary = 1` directly, which ignores the project's own documented tie-break rule for multi-primary rows (`StudentLifecycleProjection`: highest `id` wins when more than one enrollment is flagged primary). A student with two `is_primary` rows could get listed on the worklist even though they were already classified via their actual-primary enrollment — and `InitializeStudentPlacementAction`'s unordered `firstOrFail()` would then happily write the classification onto whichever row the DB handed it, not the one the projection considers primary. Fixed by routing the join through the shared `primaryEnrollmentIdSubquery()` and adding `orderByDesc('id')` in the action, plus a regression test.

## The Brutal Truth

This is exactly the kind of bug that ships silently: it doesn't throw, doesn't fail a happy-path test, it just quietly classifies the wrong row for a student who happens to have duplicate primary flags. Nobody notices until finance or academic reporting shows a student in two states at once and someone spends an afternoon in tinker figuring out why. Glad the review step caught it — but it's a reminder that "join on the obvious flag" is a trap in this codebase specifically because the primary-enrollment tie-break is a business rule, not a DB constraint, and it lives in exactly one place (`StudentLifecycleProjection`). Any new query joining on `is_primary` without going through the shared subquery is a latent copy of this bug.

## Technical Details

- Action log write: `InitializeStudentPlacementAction` now inserts one `student_action_logs` row inside its existing DB transaction — `previous_status='pending'`, `new_status=stage`, action type `STUDENT_ENROLLMENT_NE`, `from_semester_id` from the request. Matches the CLI backfill convention the user confirmed; retires manual `students:create-egc-action-logs` runs going forward.
- New read path: `ListUnclassifiedStudentsQuery` + `StudentPlacementWorklistController` + `GET students-placement-worklist` (gated by `can:change_student_status`), under `app/Modules/Academic` per module-ownership rule.
- Frontend: `pages/Academic/PlacementWorklist/{Index,ClassifyStudentDialog}.vue`, `worklist-types.ts`, sidebar entry under Students group.
- Docs: student-services section added to docs-site in vi/en/ko/zh, plus freshness-marker refresh on 64 other anchored pages — required because touching `menu-sidebar.ts` trips the docs freshness gate across every anchored page (same mechanical pattern as commit `18b916301`).
- Fix commit content (uncommitted): join via `primaryEnrollmentIdSubquery()`, `orderByDesc('id')` in the action, new regression test in `PlacementOwnershipTest`.
- Final local check counts (not re-verified this session, per instructions): Progression dir 70 passed, Navigation 5 passed, Finance sidebar consumers 20 passed, `pint` + `eslint` clean.

## What We Tried

No dead ends this run — plan execution went straight through phase 1 → 2 → 3, then code review flagged the join issue on the first pass and it was fixed directly (join rewrite + ordering + test), no back-and-forth needed.

## Root Cause Analysis

Root cause of the join bug: `is_primary` is not a unique constraint at the DB level, so "primary enrollment" is a computed business concept (highest-id tie-break in `StudentLifecycleProjection`), not a column you can trust in isolation. Writing a new query against `program_enrollments.is_primary` without routing through the shared subquery reproduces a bug that's already been solved once elsewhere in the codebase — classic case of a business rule living in application logic instead of a schema constraint, so every new caller has to know to ask for it.

## Lessons Learned

- When "primary" or similarly computed status lives in app code (not a DB unique index), grep for the canonical resolver (`primaryEnrollmentIdSubquery()` here) before writing a new query that touches the same concept — don't re-derive it inline.
- `students.intake` is an FK to `semesters.id` despite the column name — factory tests silently pass with garbage without a real semester row; caught this during phase 1 tests.
- `student_action_logs.changed_by_user_id` is NOT NULL, so any non-HTTP caller of `InitializeStudentPlacementAction` (queue job, console command, tinker) will 500 if it doesn't pass `created_by_user_id`. This is a latent gap — no current caller both is non-HTTP and skips it, but the next one might.
- Test-created `program_enrollments` must set `ProgramEnrollment::LEGACY_STUDENT_SOURCE` or `MaterializeProgramEnrollmentAction` will duplicate the row on next materialization run.
- Editing `menu-sidebar.ts` is not free — it forces a freshness-marker touch across all docs-site anchored pages (68 here) due to the CI freshness gate. Budget for that whenever a sidebar entry is added.

## Next Steps

- Deferred product decisions surfaced during review, recorded but not resolved — owner: user/product:
  1. Every classification currently lands with no `decision_id` field on the dialog — confirm whether Missing Decision reporting should capture one.
  2. Admission-deferred unclassified students stay visible on the worklist per plan decision, but classifying them force-activates the student — confirm this is intended.
  3. The classify dialog's semester dropdown has no default, and `from_semester_id` now drives lifecycle status replay — confirm the UX default is acceptable as-is.
- Commit the phase 1-3 changes plus the join fix (currently uncommitted on `dev`).
- Consider adding a guard/default for `created_by_user_id` in `InitializeStudentPlacementAction` before any non-HTTP caller is added, to close the latent 500 gap.

## AgentWiki Publish

`agentwiki whoami` returned `command not found` — no AgentWiki CLI installed in this environment, and no AgentWiki MCP tools were exposed in this session. AgentWiki publish skipped; local journal file at the path above is the source of truth.
