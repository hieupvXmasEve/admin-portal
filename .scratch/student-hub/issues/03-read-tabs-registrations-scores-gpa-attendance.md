# Hub read tabs: Registrations → class, Scores & GPA, Attendance

Status: ready-for-human

## Parent

`.scratch/student-hub/PRD.md`

## What to build

Complete the everyday read tabs end-to-end inside the Student Hub. Each tab is a Query → Inertia prop → UI path that plugs into the shell from issue 01.

End-to-end behavior:

- **Registrations:** courses per semester with status, filterable by academic year, semester, status, and retake. Each registration row **links to its course offering (class)** — the missing student → class bridge.
- **Scores & GPA:** per-course scores with assessment breakdown, plus GPA history and academic standing, in one tab. The currently-commented GPA view is enabled and merged here. Show **only this Student's** grades — cohort-level grade statistics stay in the management Reports area.
- **Attendance:** per-course attendance summary with drill-into detail.

## Acceptance criteria

- [ ] Registrations lists courses per semester with status and the year/semester/status/retake filters.
- [ ] Each registration row links to its course offering.
- [ ] Scores & GPA shows per-course scores + assessment breakdown + GPA history + academic standing in one tab; GPA is no longer hidden.
- [ ] Only this Student's grades appear; no cohort statistics in the Hub.
- [ ] Attendance shows per-course summary with detail drill-in.
- [ ] Feature tests at the Query seam for each tab's data contract, following prior art `StudentAcademicSummaryScoresTest` and `GetStudentAttendanceQueryTest`.

## Blocked by

- `.scratch/student-hub/issues/01-hub-shell-overview-and-role-aware-rendering.md`

## Comments

### Progress (implemented via `/implement`, commit `3953ae0d`)

All six acceptance criteria met. Much of the surface was already built by prior work; this slice completed, corrected, and locked it:

- **Registrations** — filters and the per-row class link already existed. **Fixed a real bug**: the `semester_id` filter threw an ambiguous-column SQL error after the `academic_records` join — `getRegistrations` now table-qualifies `course_registrations.{semester_id,registration_status,is_retake}`. The row→class link now uses Ziggy `route('course-offerings.show', …)` instead of a hardcoded URL (the student→class bridge); removed a stray `console.log`.
- **Scores & GPA** — the data contract (`getScores`) and `ScoresTab` already merged cumulative GPA + per-semester standing + per-course scores + assessment-breakdown dialog. Relabelled the tab **"Scores & GPA"** and added a consolidated **GPA-history** transcript section. Retired the now-orphaned standalone GPA surface (route, controller method, `Gpa.vue`/`GpaTab.vue`, dead `getGpaData`/`getGpaTranscript`/`calculateGpaTrend`) — GPA is merged, not duplicated (ADR-0007).
- **Attendance** — already met (per-unit summary + drill-in dialog); no code change.
- **Tests** (Query seam): `StudentHubRegistrationsTest` (class link, filters, student isolation) and `StudentHubScoresGpaTest` (merged scores + breakdown + GPA history + standing, student isolation). Both green.

**Caveat:** the attendance prior-art test `GetStudentAttendanceQueryTest` is **pre-existing red** on a `class_sessions` CHECK-constraint (`check_sessions_expected_attendees_positive` vs `AttendanceService` writing `expected_attendees = 0`) — unrelated to this change; spun off as a separate task. The whole-suite and whole-`tests/Feature/Academic` `--compact` runs abort (exit 255) in this environment, so verification was per-file.
