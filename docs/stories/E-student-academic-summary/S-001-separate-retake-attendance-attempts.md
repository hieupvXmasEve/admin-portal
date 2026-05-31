# S-001 Separate Retake Attendance Attempts

## Status

implemented

## Lane

normal

## Product Contract

Student academic summary attendance must show each course attempt separately. A retake of the same unit must not merge attendance sessions from the earlier attempt.

The attendance summary overall rate should be explicit and match the displayed aggregate counts.

## Relevant Product Docs

- `docs/project-overview-pdr.md`
- `docs/system-architecture.md`
- `docs/code-standards.md`
- `docs/design-guidelines.md`
- `docs/inertiajs-vue-info.md`

## Acceptance Criteria

- Attendance rows are grouped by course offering/attempt, not only by unit.
- Retake rows for the same unit can be expanded and opened independently in the UI.
- Overall attendance percentage is calculated from total attended sessions divided by total sessions, matching the summary card counts.
- Existing status thresholds remain unchanged: excellent >= 90, good >= 80, warning >= 70, critical otherwise.

## Design Notes

- Commands:
- Queries: update `App\Modules\Academic\Queries\GetStudentAttendanceQuery`.
- API: no new route required; preserve existing Inertia page prop shape with additive fields only if needed.
- Tables: read-only use of `attendances`, `class_sessions`, `course_offerings`, `units`, `semesters`.
- Domain rules: `present` and `late` count as attended; `absent` and `excused` do not.
- UI surfaces: `resources/js/pages/students/AcademicSummary/AttendanceTab.vue`.

## Validation

| Layer | Expected proof |
| --- | --- |
| Unit | Targeted PHP test for `GetStudentAttendanceQuery` covering same unit across original attempt and retake. |
| Integration | Inertia/page contract remains consumable by existing attendance page. |
| E2E | Manual browser check if local auth/session is available. |
| Platform | Not applicable. |
| Release | Targeted lint/type/format checks where practical. |

## Harness Delta

No harness changes expected.

## Evidence

- `./scripts/dev.sh test tests/Feature/Academic/GetStudentAttendanceQueryTest.php` passed: 1 test, 19 assertions.
- `./scripts/dev.sh composer exec pint -- --test app/Modules/Academic/Queries/GetStudentAttendanceQuery.php app/Modules/Academic/Queries/GetStudentAttendanceDetailsQuery.php app/Modules/Academic/Http/Web/StudentAcademicSummaryController.php tests/Feature/Academic/GetStudentAttendanceQueryTest.php` passed.
- `./scripts/dev.sh npm exec -- eslint resources/js/pages/students/AcademicSummary/AttendanceTab.vue resources/js/types/models.ts` passed.
- `./scripts/dev.sh npm exec -- prettier --check resources/js/pages/students/AcademicSummary/AttendanceTab.vue resources/js/types/models.ts` passed.
- `git diff --check` passed.
- `./scripts/dev.sh npm run type-check` failed with Node heap OOM at default heap size.
- `./scripts/dev.sh npm exec -- node --max-old-space-size=4096 node_modules/vue-tsc/bin/vue-tsc.js --noEmit` completed but failed on pre-existing repo-wide type drift; no diagnostics were reported for `AttendanceTab.vue` or `resources/js/types/models.ts`.
