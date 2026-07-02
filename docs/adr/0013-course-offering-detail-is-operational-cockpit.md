# Course Offering Detail is the operational cockpit

**Context.** Course-offering management is currently split across the Course Offering detail page, Course Statistics pages, the Attendance pages, Canvas administration, and several web-backed API routes — roughly 44 distinct entry points touch a single `CourseOffering`. Staff-facing actions such as roster changes, sessions, attendance, scores, completion, surveys, and Canvas-linked constraints appear as separate flows even though they all operate on one `CourseOffering`.

This split makes the user journey unclear, and frontend lifecycle hints drift from backend completion rules: `CourseLifecycleHeader.vue` infers lifecycle stage and "ready to finalize" from raw props, while `MarkCourseOfferingCompletedAction` enforces stricter rules (attendance actually recorded, no auto-system-only sessions). Completion/recalculation currently has no UI entry point at all, and Canvas sync state silently changes how grades are aggregated at finalization.

**Decision.** Treat the Course Offering detail page as the **Course Offering Cockpit**: the only operational surface for managing one `CourseOffering`. Course Statistics and Attendance are retained as report/export surfaces, not as competing management flows.

The cockpit gets its operational state from a backend read model that exposes the course lifecycle, `readiness_blockers`, and `available_actions` as an explicit contract, rather than asking Vue components to infer whether actions are allowed.

**Boundaries.**

- **Course Offering Detail owns operations.** Setup, roster changes, session management, **attendance recording**, scores, completion/recalculation entry points, surveys, and Canvas-linked operation blockers are presented from the Course Offering detail page. The standalone Attendance pages become cross-offering reporting only; per-offering attendance is recorded from the cockpit.
- **Course Statistics owns reporting.** Course Statistics may aggregate, drill down, and export attendance/scores/reporting data, and links to the cockpit when a staff member needs to act. Its per-offering assessment-scores page is retired in favor of the cockpit scores tab (redirect); Excel exports remain under Course Statistics.
- **Backend lifecycle is the source of truth, derived not persisted.** A backend Query derives the lifecycle stage (setup → registration → teaching → grading → completed / cancelled) from `course_status`, sessions, and attendance. No new lifecycle column; the existing frontend inference in `CourseLifecycleHeader.vue` is deleted, not mirrored.
- **One contract, one delivery path.** The cockpit receives a single `operational_state` Inertia prop (lifecycle, `readiness_blockers`, `available_actions`) and refreshes it with a partial reload after every mutating action, including API-modal actions. No parallel JSON endpoint for the same data.
- **Action semantics.** Actions blocked by state are sent with `blocked_by` references to blockers so the UI can disable and explain them. Actions the staff member lacks permission for are omitted entirely.
- **Canvas sync is a hard completion blocker for mapped offerings.** An offering with a Canvas mapping that has not synced cannot be finalized. This is a deliberate business-rule change (previously finalization silently fell back to manually entered grades) and is enforced in the completion Action, not only in the UI.
- **Completion is a first-class cockpit action.** Finalize is a routine action, enabled when blockers clear. Recalculate appears only for already-completed offerings, behind a stricter dedicated permission, with an explicit confirmation describing side effects.
- **Reports link to operations, not the reverse.** Report pages deep-link to the cockpit for action. The cockpit never requires staff to visit a report page to complete routine course-offering work.

**Why this shape.** A `CourseOffering` is an operational unit, while Course Statistics is an analytical view. One cockpit removes menu-scatter and stops staff from guessing which page owns the next action. Deriving lifecycle in a read model avoids a new persisted column that could drift from session/attendance reality, and a single prop-based contract avoids maintaining two delivery paths for the same state. Keeping reports separate prevents the cockpit from becoming a dashboard/export workspace.

**Phasing.** (A) operational-state read model + Finalize/Recalculate entry points + Canvas blocker rule → (B) absorb attendance recording into the cockpit, demote Attendance pages to reporting → (C) retire the Statistics assessment-scores page and clean up menus.

**Unresolved questions.**

- Exact Canvas blocker predicate: which `CanvasCourseMapping.sync_status` values (pending / mapped / ignored) combined with `is_canvas_synced` count as "mapped but not synced", and how offerings already mid-lifecycle (or completed while unsynced) are treated. Needs academic sign-off since it tightens an existing rule.
- Recalculate idempotency: `CourseCompletionService::finalizeCourse()` also runs EGC progression, survey attachment, and notifications; re-running must not double-send or re-trigger these. Audit before exposing the button.
- Name and scope of the new recalculate permission.
- Lecturer attendance flow (`/api/v1/lecturer/*`) is assumed unaffected by phase B; verify before demoting the staff Attendance pages.
