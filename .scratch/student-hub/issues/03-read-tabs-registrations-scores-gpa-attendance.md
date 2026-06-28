# Hub read tabs: Registrations → class, Scores & GPA, Attendance

Status: ready-for-agent

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
