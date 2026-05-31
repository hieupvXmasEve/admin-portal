# Test Matrix

This file maps product behavior to proof.

No product behavior has been defined or implemented yet. Do not mark a row
implemented until tests or validation evidence exist.

## Status Values

| Status      | Meaning                                        |
| ----------- | ---------------------------------------------- |
| planned     | Accepted as intended behavior, not implemented |
| in_progress | Actively being built                           |
| implemented | Implemented and proof exists                   |
| changed     | Contract changed after earlier implementation  |
| retired     | No longer part of the product contract         |

## Matrix

| Story                                      | Contract                                                                                                                              | Unit | Integration | E2E | Platform | Status      | Evidence                                                                                                                                                |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------- | ---- | ----------- | --- | -------- | ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| S-001-course-survey-class-result-download  | Admin/staff can download per-class course survey aggregate results without raw student identifiers                                    | yes  | yes         | no  | no       | implemented | `tests/Feature/Form/SurveyResultDownloadTest.php`; targeted Pint, ESLint, Prettier passed; repo-wide `vue-tsc` still fails on pre-existing typing drift |
| S-001-filter-students-by-multiple-codes    | Staff can paste multiple student codes on the campus-scoped student directory and preserve the filter through pagination and export   | yes  | yes         | no  | no       | implemented | `tests/Feature/Academic/ListStudentsQueryTest.php`, `tests/Feature/Academic/StudentDirectoryFilterTest.php`; 6 tests/25 assertions passed; targeted Pint, ESLint, Prettier, and `git diff --check` passed; repo-wide `vue-tsc` still fails on pre-existing typing drift |
| S-001-filter-lecturers-by-semester-program | Staff can filter the lecturer directory by assigned semester and teaching program/unit type, including EGC, within the current campus | yes  | yes         | no  | no       | implemented | `tests/Feature/Lecture/ListLecturesQueryTest.php`; targeted Pint, ESLint, Prettier passed; repo-wide `vue-tsc` still fails on pre-existing typing drift |
| S-001-guard-canvas-syllabus-template-reuse | Course offering create/edit excludes Canvas-reserved syllabus templates and backend validation blocks direct reuse across classes     | yes  | yes         | no  | no       | implemented | `tests/Feature/CourseOffering/CanvasSyllabusTemplateGuardTest.php`; 6 tests/36 assertions passed; targeted Pint and `git diff --check` passed             |
| S-002-advanced-student-directory-filters   | Staff can combine multi-select program, specialization, status, and intake filters from a sheet and remove applied filters as chips   | yes  | yes         | no  | no       | implemented | `tests/Feature/Academic/ListStudentsQueryTest.php`, `tests/Feature/Academic/StudentDirectoryFilterTest.php`; 11 tests/72 assertions passed; targeted Pint, ESLint, Prettier, build, and `git diff --check` passed; repo-wide `vue-tsc` has pre-existing drift |
| S-001-separate-retake-attendance-attempts  | Student academic summary attendance shows each course attempt separately and overall rate matches aggregate attended/total sessions   | yes  | yes         | no  | no       | implemented | `tests/Feature/Academic/GetStudentAttendanceQueryTest.php`; targeted Pint, ESLint, Prettier, and `git diff --check` passed; repo-wide vue-tsc fails on pre-existing typing drift |

## Evidence Rules

- Unit proof covers pure domain and application rules.
- Integration proof covers backend enforcement, data integrity, provider
  behavior, jobs, or service contracts.
- E2E proof covers user-visible browser flows.
- Platform proof covers only shell, deployment, mobile, desktop, or runtime
  behavior that cannot be proven in lower layers.
- A story can be implemented without every proof column if the story packet
  explains why.
