# S-001 Active Semester Teaching Hours Summary Export

## Status

implemented

## Lane

normal

## Product Contract

The `/lectures/teaching-hours` report defaults to the active semester and leaves
date filters blank until staff choose a custom range. The summary table shows
one row per lecturer with identity, employment type, taught courses in the
selected semester/range, and total teaching hours. Staff can export the current
filtered view to Excel.

## Relevant Product Docs

- `docs/product/lecturers.md`

## Acceptance Criteria

- When the page opens without query parameters, `Semester` is set to the active
  semester and `From Date` / `To Date` are blank.
- The summary table includes `No`, `Lecturer name`, `Email account`,
  `Employee ID`, `Type`, `Course dạy`, `Môn giảng dạy của GV`, and
  `Total hours`.
- Course columns reflect the courses taught by the lecturer in the current
  filter scope; for example, a lecturer who teaches AU001 and AU002 in the
  selected semester shows both codes.
- If staff choose a date range such as `2026-04-16` to `2026-05-15`, total
  hours and taught courses are calculated only from sessions in that range.
- Excel export downloads the same current filtered view.

## Design Notes

- Commands: none.
- Queries: extend the existing teaching-hours read action/query behavior.
- API: existing Inertia web route plus a new protected Excel download route.
- Tables: read from `class_sessions`, `lectures`, `course_offerings`, `units`,
  and `semesters`; no schema change.
- Domain rules: default semester uses `Semester::getActiveSemester()`;
  date filters are optional and apply only when provided.
- UI surfaces: `resources/js/pages/lectures/TeachingHours.vue`.

## Validation

| Layer       | Expected proof                                                                                                       |
| ----------- | -------------------------------------------------------------------------------------------------------------------- |
| Unit        | Teaching-hours query/action test for active-semester default, optional dates, course list, and date-range filtering. |
| Integration | Feature/export test proving Excel download uses the current filter contract.                                         |
| E2E         | Manual smoke at `/lectures/teaching-hours` if local app is available.                                                |
| Platform    | Not required.                                                                                                        |
| Release     | Targeted Pint, ESLint/Prettier for touched files, and type-check if feasible.                                        |

## Harness Delta

No harness process change expected.

## Evidence

- `./scripts/dev.sh test tests/Feature/Lecture/GetTeachingHoursActionTest.php`
  passed: 3 tests, 18 assertions.
- `./scripts/dev.sh composer exec pint -- app/Actions/Lecture/GetTeachingHoursAction.php app/Http/Controllers/Web/LectureController.php app/Http/Controllers/Web/Lectures/LectureExportController.php app/Http/Requests/Lecture/ViewTeachingHoursRequest.php app/Services/LectureTeachingHoursExcelExportService.php routes/web/lectures.php tests/Feature/Lecture/GetTeachingHoursActionTest.php`
  passed after fixing 3 style issues.
- `./scripts/dev.sh npm exec eslint resources/js/pages/lectures/TeachingHours.vue`
  passed.
- `./scripts/dev.sh npm exec prettier --check resources/js/pages/lectures/TeachingHours.vue docs/product/lecturers.md docs/stories/E-lecturer-teaching-hours/S-001-active-semester-teaching-hours-summary-export.md docs/TEST_MATRIX.md`
  passed.
- `git diff --check -- <teaching-hours touched files>` passed.
- `./scripts/dev.sh npm run type-check` failed with Node heap OOM; rerun with
  `./scripts/dev.sh npm exec node --max-old-space-size=4096 ./node_modules/vue-tsc/bin/vue-tsc.js --noEmit`
  completed and failed on repo-wide pre-existing TypeScript diagnostics, with no
  diagnostics reported for `resources/js/pages/lectures/TeachingHours.vue`.
