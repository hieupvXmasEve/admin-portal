# Hub Lifecycle tab: unified timeline + in-place actions & EGC placement/progression

Status: ready-for-human

## Parent

`.scratch/student-hub/PRD.md`

## What to build

The Lifecycle tab as a single chronological timeline merging the two lifecycle streams, with in-place actions. Respect ADR-0009 (unified timeline) and ADR-0008 (Decision model).

End-to-end behavior:

- One chronological **timeline merged from `StudentActionLog` and `AcademicProgressionEvent`**, ordered by event time, with authorizing **Decisions shown inline** on the transitions they authorize. EGC English-level and IELTS history render in a **sub-panel** under the timeline, not in the main timeline.
- Record **Student Actions** (defer, resume, dropout, campus transfer, admission deferral) in place.
- Manage **EGC placement/progression** (initialize Placement, record IELTS, change English level, transition into `intake_course`) in place. EGC controls appear **only for Students whose status is `intake_pre_uni_gc`**.
- A requires-decision transition can be recorded **without** a Decision and **backfilled** later (uses the model from issue 02); transitions still missing their required Decision are visibly flagged.
- The retired standalone routes `/students/{id}/actions` and `/students/{id}/placement` **redirect** into this tab.

## Acceptance criteria

- [x] The Lifecycle tab shows one chronological timeline merged from Student Actions + Academic Progression, ordered by event time.
- [x] Authorizing Decisions render inline on the transitions they authorize; EGC level/IELTS history is a sub-panel.
- [x] Staff can record Student Actions and EGC placement/progression in place without leaving the Student.
- [x] EGC controls appear only for `intake_pre_uni_gc` Students.
- [x] A requires-decision transition records with no Decision and can be backfilled; the missing state is visible.
- [x] `/students/{id}/actions` and `/students/{id}/placement` redirect into the Lifecycle tab.
- [x] Tests at the Action seam (record action/progression, soft requires-decision, EGC gating) and Query seam (merged timeline ordering).

## Comments

### Implemented (handoff)

- **Query seam** — `GetStudentLifecycleTimelineQuery` merges `StudentActionLog` + status-changing `AcademicProgressionEvent` (PLACEMENT_INITIALIZED, COURSE_STAGE_CHANGED) into the main timeline; ENGLISH_LEVEL_CHANGED / IELTS_RECORDED go to the EGC sub-panel. Deterministic newest-first ordering (event time, then source, then id). Decisions inline + `missing_decision` flag.
- **Action seam** — `AttachDecisionToTransitionAction` backfills a Decision onto an action log or progression event and covers the student on the roster (ADR-0008). Recording reuses the existing `RecordStudentActionAction` / placement actions via the retained POST endpoints.
- **Controller / routes** — `StudentAcademicSummaryController@lifecycle` + `@attachDecision`; `students.academic-summary.lifecycle(.attach-decision)`. `StudentActionController@index` and `AcademicPlacementController@show` now redirect into the tab (POST write endpoints kept). Shared `LifecycleFormOptions` extracted (DRY).
- **Frontend** — data-driven `lifecycle` hub tab; timeline list with inline decisions + missing-decision backfill dialog; EGC sub-panel; in-place Record Action and EGC placement/progression dialogs (EGC controls gated to `intake_pre_uni_gc` + `change_student_status`).
- **Tests (all green)** — `StudentLifecycleTimelineQueryTest` (5), `AttachDecisionToTransitionActionTest` (3), `StudentHubLifecycleTabTest` (3). Full Academic-root suite green except the pre-existing `GetStudentAttendanceQueryTest` CHECK-constraint failure (unrelated). Pint + eslint + production build clean.
- **Manual QA still needed** — the Vue tab is verified by build/eslint only (no FE unit harness); a human should click through the timeline, record dialogs, and EGC controls.

### Status-aware action rules (clarified)

Follow-up to QA feedback: the record dialog must offer only the next actions that make sense for the student's *current* status (not all eight), and the backend must reject illogical transitions.

- **Backend is the authority.** `RecordStudentActionAction::validateStatusTransitionPolicy` already rejects illegal transitions (e.g. `ACADEMIC_RESUME` is only allowed from `deferred` / `pending_course_opening`, so resume-while-`intake_course` throws). Covered by a new test.
- **Single source of truth for the UI list.** `App\Modules\Academic\Support\StudentStatusTransitionPolicy::selectableActions(status)` is the curated next-action list per status — it replaces the old page's Vue-only `availableActions` (which fell back to "show all", the cause of nonsensical picks). The controller passes `options.action.allowedActionTypes`; the dialog filters the dropdown to it and disables recording when the set is empty.
- **Curated sets:** `pending → {NE enrollment, admission deferral, dropout}`; `intake_pre_uni_gc` / `intake_course → {waiting course opening, defer, dropout, campus transfer}`; `pending_course_opening → {resume, defer, dropout}`; `deferred → {resume, defer}`; terminal (`dropout`/`dropout_transfer`/`graduated`) and any other status → none.
- **Deliberately excluded from the dropdown:** stage moves (`STUDENT_MAJOR_ENROLLMENT`, pre-uni ⇄ course) — those are recorded by the EGC progression controls, so they are not offered here to avoid two ways to do the same thing. The backend still records them programmatically.

## Blocked by

- `.scratch/student-hub/issues/01-hub-shell-overview-and-role-aware-rendering.md`
- `.scratch/student-hub/issues/02-decision-model-and-missing-decision-report.md`
