# Operational state read model + cockpit displays lifecycle, blockers, and gated Finalize button (display only)

Status: ready-for-human (implemented 2026-07-02; all acceptance criteria met, see commit on dev)

## Parent

`.scratch/course-offering-cockpit/PRD.md` (Course Offering Cockpit)

## What to build

A new backend read model (Academic module Query) that derives the Course Offering's operational state — lifecycle stage, readiness blockers, and available actions — from existing data (`course_status`, sessions, attendance, Canvas mapping). No new persisted lifecycle column.

Deliver this as a single `operational_state` Inertia prop on the Course Offering detail page (the Course Offering Cockpit). The frontend renders the backend-derived lifecycle stage and a list of readiness blockers, each carrying a stable machine code, a human message, and references to the offending objects (e.g. session ids). The existing frontend lifecycle/warning inference in `CourseLifecycleHeader.vue` is deleted, not mirrored — the component renders the backend contract only.

Readiness blockers computed in this slice:
- Sessions without recorded attendance.
- Sessions with attendance recorded only via `recording_method = auto_system`.
- Canvas-mapped-but-unsynced: a `CanvasCourseMapping` with `sync_status = mapped` exists and the offering is not synced. `pending` and `ignored` mappings do not block. Offerings without a mapping are unaffected.

Add a new permission `complete_course_offering` (verb_entity convention, granted by default to roles holding `edit_course_offering`), following the pattern in `config/permission.php` and `database/seeders/InitialSetup/RoleAndPermissionSeeder.php`.

Render a Finalize button in the cockpit, following the `available_actions` shape precedent from `ListRetakeCourseRegistrationsQuery::deriveAvailableActions()` (Finance module has an equivalent pattern) — extend that shape to carry an `allowed` flag and `blocked_by` blocker codes, since the existing precedent is a flat action-name array and this contract needs blocked-with-reason semantics. The Finalize button:
- Is omitted entirely if the current user lacks `complete_course_offering`.
- Is visible but disabled with an explanation (blocker messages) when readiness blockers are present.
- Does **not** execute anything yet in this slice — wiring the click to the completion action is a separate issue.

## Acceptance criteria

- [x] A backend Query returns `operational_state` with `lifecycle_stage`, `readiness_blockers[]` (code, message, references), and `available_actions[]` (action key, `allowed`, `blocked_by[]`) for a Course Offering. (`GetCourseOfferingOperationalStateQuery`)
- [x] The Course Offering detail page passes `operational_state` as a single Inertia prop; no parallel JSON endpoint exists for the same data.
- [x] `CourseLifecycleHeader.vue`'s frontend-computed lifecycle/warning logic is removed; the header renders only what the backend prop provides.
- [x] Cockpit displays the lifecycle stage and the readiness blockers list, each blocker showing its human message and linking to/naming the referenced sessions.
- [x] Canvas-mapped-but-unsynced offerings show the Canvas blocker; `pending`/`ignored`-mapped and unmapped offerings show no Canvas blocker.
- [x] `complete_course_offering` permission exists, is seeded, and is granted by default to roles holding `edit_course_offering` (both fresh-install and live-DB sync seeders).
- [x] Finalize button is omitted for users without `complete_course_offering`; shown-and-disabled with blocker explanations for users who have the permission but whose offering has blockers.
- [x] Pest HTTP feature test asserts the `operational_state` prop shape across scenarios: fresh offering (setup), sessions without recorded attendance, sessions with only auto-system attendance, Canvas mapped-but-unsynced, and fully ready (no blockers).
- [x] Tests use `_token` for CSRF where applicable (this slice is GET-only, CSRF middleware disabled per prior art) and are run as explicit files/subdirectories.

## Blocked by

None - can start immediately
