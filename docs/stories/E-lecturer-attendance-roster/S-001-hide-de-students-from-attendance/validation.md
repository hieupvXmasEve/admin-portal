# Validation

## Status

Superseded by `S-002-show-inactive-students-in-roster`.

The active-count and marking-guard validation remains relevant. The omission
assertions were replaced by `S-002` assertions that inactive students remain in
responses with non-markable roster metadata.

## Proof Strategy

Use backend feature tests for the lecturer attendance/course roster APIs. The
tests create active and DE students in the same EGC/Major-style course offering
and assert only active students are counted or markable.

Use portal validation only if lecturer FE code changes. Removing obsolete FE
agent workflow files is validated through git status/search rather than a Nuxt
build.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Any extracted roster eligibility helper covers active vs defer/dropout statuses. |
| Integration | Lecturer session attendance and course students APIs omit DE students and keep active students. |
| E2E | Not required unless UI code changes. |
| Platform | `FE/student-nuxt/.agent` removed; no `.agent`, `.claude`, `.agents`, or `.khuym` workflow packs remain in FE repos unless explicitly justified. |
| Performance | Queries should use existing relationships/scopes, not per-student checks in loops. |
| Logs/Audit | Not applicable; no audit behavior changes expected. |

## Fixtures

- One lecturer.
- One EGC or Major course offering with an active student.
- One matching course offering/session with a DE student.
- Existing course registration/attendance records needed by current factories.

## Commands

```text
./scripts/dev.sh test tests/Feature/Lecturer/LecturerRosterInactiveStudentTest.php
./scripts/dev.sh composer exec pint -- app/Models/CourseOffering.php app/Models/CourseRegistration.php app/Models/Student.php app/Services/AttendanceService.php app/Services/V1/Lecturer/LecturerAttendanceService.php app/Services/V1/Lecturer/LecturerCourseService.php app/Services/V1/Lecturer/LecturerDashboardService.php app/Services/V1/Lecturer/LecturerTimetableService.php app/Http/Resources/Api/V1/Lecturer/CourseOfferingResource.php app/Http/Resources/Api/V1/Lecturer/AttendanceSessionResource.php app/Http/Controllers/Api/V1/Lecturer/CourseController.php tests/Feature/Lecturer/LecturerRosterInactiveStudentTest.php
git diff --check -- <touched backend/docs/story files>
cd FE/lecturer-nuxt && pnpm typecheck
```

## Acceptance Evidence

- `./scripts/dev.sh test tests/Feature/Lecturer/LecturerRosterInactiveStudentTest.php` passed: 4 tests, 17 assertions.
- Targeted Pint passed after fixing 7 style issues across touched PHP files.
- `git diff --check -- <touched backend/docs/story files>` passed.
- `cd FE/lecturer-nuxt && pnpm typecheck` passed. Nuxt emitted pre-existing duplicate component/import warnings, but exit code was 0.
- `find FE/student-nuxt FE/lecturer-nuxt -maxdepth 3 \( -name '.claude' -o -name '.agents' -o -name '.khuym' -o -name '.agent' -o -name '.opencode' \) -print` returned no workflow packs.
