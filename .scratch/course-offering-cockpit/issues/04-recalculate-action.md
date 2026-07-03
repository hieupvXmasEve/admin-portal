# Recalculate action

Status: ready-for-human

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## What to build

Add a Recalculate action to the cockpit for already-completed offerings. Add a new, stricter permission `recalculate_course_offering` (verb_entity convention, assigned narrowly — not granted by default alongside `edit_course_offering`).

The Recalculate button is omitted entirely for users without the permission. For users with the permission, clicking it opens a confirmation dialog that explicitly describes the side effects (grades are recalculated; only students whose pass/fail status changed are notified; survey is not re-attached; EGC progression is re-evaluated against the previous status). On confirmation, it calls `MarkCourseOfferingCompletedAction` with `recalculate: true`.

The Canvas completion rule applies to Recalculate the same as Finalize: blocked when a `CanvasCourseMapping` with `sync_status = mapped` exists and the offering is not synced.

Do not proceed with shipping this button until the EGC recalculate progression audit has reached a verdict; if the audit found unsafe behavior with an unresolved follow-up, coordinate with that issue before wiring the button live (either wait for the fix or explicitly scope this issue to ship with a documented known limitation, per user direction).

## Acceptance criteria

- [x] `recalculate_course_offering` permission exists, is seeded, and is assigned narrowly (not bundled with `edit_course_offering` by default).
- [x] Recalculate button appears only on already-completed offerings, only for users with `recalculate_course_offering`.
- [x] Confirmation dialog lists the side effects (recalculated grades, selective notification, no survey re-attach, EGC re-evaluation) before the action runs.
- [x] Recalculate is blocked by the same Canvas mapped-but-unsynced rule as Finalize.
- [x] Successful recalculation refreshes `operational_state` via the same Inertia partial-reload mechanism used by Finalize.
- [x] Notifications are sent only to students whose pass/fail status actually changed as a result of the recalculation (existing `CourseCompletionService` guarantee — verified, not reimplemented).
- [x] EGC progression re-evaluates against the previous-status map without duplicate progression events, consistent with the audit's verdict.
- [x] Pest HTTP feature tests assert: 403 for missing `recalculate_course_offering`, Canvas blocker enforcement, success path, and that only status-changed students are notified.

## Blocked by

- Finalize executes from the cockpit
- EGC recalculate progression audit

## Comments

### EGC recalculate progression audit resolved (2026-07-03)

Verdict: **unsafe**. Promotion-on-correction (fail→pass) and repeated-recalculate idempotency are both safe; but a demotion-on-correction (pass→fail) leaves a student's `gc_current_level` incorrectly stuck at the promoted level with no reversal event or accurate notification. Full findings: `.scratch/course-offering-cockpit/issues/03-egc-recalculate-progression-audit.md`. Follow-up fix filed: `.scratch/course-offering-cockpit/issues/09-fix-egc-level-demotion-on-recalculate.md` (not yet started).

Per this issue's own note above, whoever picks this issue up needs a decision from the PRD owner: wait for issue 09's fix, or ship Recalculate now with the demotion gap as a documented known limitation (e.g. surfaced in the confirmation dialog copy).

### Implemented (2026-07-03)

PRD owner chose to implement issue 09's fix and this issue together rather than ship with a documented known limitation — see issue 09's Comments for the demotion-fix design decisions.

- Permission: `recalculate_course_offering` added to `config/permission.php` and left out of `RoleAndPermissionSeeder::assignPermissionsToOtherRoles()` entirely (only `super_admin` holds it by default) — deliberately narrower than `complete_course_offering`'s "follows `edit_course_offering`" grant.
- Backend: `RecalculateCourseOfferingController` (mirrors `FinalizeCourseOfferingController`) at `POST /course-offerings/{courseOffering}/recalculate`, gated by `can:recalculate_course_offering`, calls `MarkCourseOfferingCompletedAction::run($courseOffering, recalculate: true)`.
- Canvas rule fix: `MarkCourseOfferingCompletedAction`'s Canvas check was previously gated on `course_status !== 'completed'`, which silently exempted Recalculate (a bug relative to the PRD's "Finalize and Recalculate are both blocked" decision, introduced while implementing Finalize in issue 02). Removed that gate — the check now applies whenever execution reaches it, which by construction is only on an explicit Finalize or Recalculate action, never as backfill.
- Read model: `GetCourseOfferingOperationalStateQuery` now exposes a `recalculate` action (gated by `recalculate_course_offering`) for `completed` offerings, blocked only by the Canvas rule (attendance blockers don't re-apply — they were already satisfied at the original finalize).
- Frontend: `CourseLifecycleHeader.vue` generalized its single Finalize button into an `activeAction` that renders whichever action the backend sends (`finalize` or `recalculate`), with a Recalculate-specific confirmation dialog listing the side effects.
- Tests: `tests/Feature/CourseOffering/CourseOfferingRecalculateTest.php` (403, Canvas blocker, success + partial reload, permission-gated visibility, selective notification via a `PublishDomainEventAction` spy — outbox-row assertions don't work here since `runAfterCommit`'s deferred callback never fires under `RefreshDatabase`'s test transaction), `tests/Feature/CourseOffering/RecalculateCourseOfferingPermissionSeederTest.php`, and an updated `CourseOfferingFinalizeTest.php` (the old "does not retroactively apply the Canvas rule when recalculating" test asserted the pre-fix behavior and was rewritten).
