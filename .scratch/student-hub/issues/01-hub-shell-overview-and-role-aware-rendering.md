# Student Hub shell: single surface + context bar + Overview + role-aware rendering

Status: ready-for-agent

## Parent

`.scratch/student-hub/PRD.md`

## What to build

The walking skeleton and prefactor for the whole feature: establish the **Student Hub** as the one canonical student-detail surface, with the tracer bullet running route → controller → Query → Inertia page → layout (context bar + tab framework) → Overview tab. Respect ADR-0007.

End-to-end behavior:

- Opening a Student lands on one canonical Hub (the upgraded academic-summary surface) with a **persistent context bar** on every tab: photo, name, student id, status, intake, program/specialization, plus quick actions (login-as-student, edit, export placeholder).
- The **Overview** tab shows identity, contacts, holds, academic info (campus, program, specialization, curriculum version), key stats, and academic-concern alerts (active holds, probation/warning/suspension). The useful fields from the orphan Show page (emergency contacts, recent registrations, holds) are folded into Overview.
- The orphan Show page is **removed**; nothing links to it.
- A single "Student" entry in the navigation leads to the Hub.
- **Role-aware rendering:** the Cán bộ Đào tạo sees the full Hub; act controls are gated by `change_student_status` / `view_student_action`; other roles see a reduced read-only field set (gated by `view_student_summary`).

Prefactor (do first, "make the change easy"): turn the tab bar + context bar into a clean, data-driven extension point so later slices add tabs without touching the shell, and consolidate the per-student route group. The existing 6 academic-summary tabs keep working through the new shell.

## Acceptance criteria

- [ ] Opening a Student shows one canonical Hub with a persistent context bar present on every tab.
- [ ] Overview shows identity, contacts, holds, academic info, key stats, and academic-concern alerts; the useful Show-page fields are present.
- [ ] The orphan Show page is removed and unreferenced.
- [ ] A single "Student" nav entry leads to the Hub.
- [ ] Act controls render only with the right permission; other roles get a reduced read-only field set.
- [ ] The tab framework is a data-driven extension point (a new tab can be added without editing the shell) and per-student routes are consolidated.
- [ ] Feature tests cover the Overview Query data contract and role-aware visibility, asserting external behavior; the existing academic-summary endpoint smoke test still passes.

## Blocked by

None - can start immediately.

## Comments

### Progress (handoff to a fresh `/implement` session)

- ✅ **Orphan Show page retired.** `resources/js/pages/students/Show.vue` deleted and the dead web branch of `StudentController@show` removed — no web route mapped to it (only `apiShow` is routed). Committed in `3e5180a8`. Acceptance criterion "orphan Show page removed and unreferenced" is **done + verified** (`route:list` resolves; `tests/Feature/Academic` green; Pint passed). The remaining acceptance criteria are still open.

### Implementation notes for the remaining work

- **TDD seam (reads):** Overview data comes from `StudentAcademicSummaryService::getOverviewData($student)`, rendered by `StudentAcademicSummaryController@overview` into the `students/AcademicSummary/Overview` page. Test it at the service seam (see `tests/Feature/Academic/StudentAcademicSummaryScoresTest.php`, which calls the service directly) or at the endpoint with `assertInertia`.
- **Fields to fold from the deleted Show into Overview** (confirm they are not already in `OverviewTab.vue`): recent course registrations (last 5) and the "additional info" block — `high_school_name`, `high_school_graduation_year`, `entrance_exam_score`, `admission_notes`. `OverviewTab.vue` already renders personal info, academic info, emergency contacts, stats, and concern alerts.
- **Hub shell / context bar:** `resources/js/layouts/StudentLayout.vue` is the shell (header + hardcoded `tabs` array; GPA/Graduation commented out). Upgrade the header into the persistent context bar (photo, program/specialization, quick actions); the `student` prop is currently only `Pick<Student,'id'|'student_id'|'full_name'|'status'|'email'|'intake'>` — widen it. Prefactor the `tabs` array into a data-driven, permission-aware list so issues 03/04/05/07 plug tabs in without editing the shell.
- **Role-aware view:** act controls gate via `usePermission().can('change_student_status' | 'view_student_action')`; the reduced read-only field set for other roles is still to do.

### Test-infra gotchas (verified this session)

- Feature tests need the session CSRF token (`session(['_token' => ...])`) — CSRF is active in feature tests.
- The `testing` DB connection in `phpunit.xml` is isolated; plain `./scripts/dev.sh artisan test ...` is safe. **Never** pass `--env=testing` (it loads `.env` and targets the dev/asia DB).
- Do **not** run whole-project `vue-tsc` in the dev container (OOM/SIGKILL); use per-file eslint or host/CI for FE type-checking.
