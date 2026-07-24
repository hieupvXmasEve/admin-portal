# Migrate class sessions and attendance operations

Status: ready-for-human

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut class-session scheduling, attendance recording, readiness, reporting, and student/lecturer attendance contracts over to Course Delivery & Assessment while preserving roster rules and the distinction between Academic Attendance Rate and Operational Presence Rate.

## Acceptance criteria

- [x] Staff and lecturer session/attendance mutations use one Course Delivery owner path.
- [x] Student and lecturer attendance reads preserve actor authorization, roster scope, status semantics, and response envelopes.
- [x] Attendance readiness and reporting use the glossary-defined metrics without frontend inference.
- [x] Existing routes, UI behavior, filters, exports, and notifications remain compatible.
- [x] Legacy attendance services/controllers/routes have zero supported callers before retirement.

## Blocked by

- [Migrate Course Offering setup and roster operations](09-migrate-course-offering-setup-roster.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/AttendanceDeliveryMigrationArchTest.php tests/Feature/Architecture/CourseDeliveryAssessmentBoundaryArchTest.php tests/Feature/Attendance/ClassSessionBulkUpdateAttendanceTest.php tests/Feature/Attendance/StandaloneAttendancePagesTest.php tests/Feature/Lecturer/LecturerAttendanceMarkApiTest.php tests/Feature/CourseOffering/CourseOfferingRecordAttendanceTest.php` — 27 passed (111 assertions).
- `./scripts/dev.sh npm run type-check` — passed.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed.
- `./scripts/dev.sh artisan test --compact` — exited 2 with failures outside this issue's changed surface; focused migration coverage passes.
- `./scripts/dev.sh artisan test --compact tests/Feature/Attendance/CourseOfferingAttendanceReportTest.php tests/Feature/Lecturer/LecturerRosterInactiveStudentTest.php` — 8 passed (98 assertions).
- `./scripts/dev.sh artisan test --compact tests/Feature/Attendance/CourseOfferingAttendanceReportTest.php tests/Feature/Lecturer/LecturerRosterInactiveStudentTest.php tests/Feature/Architecture/AttendanceDeliveryMigrationArchTest.php tests/Feature/Architecture/CourseDeliveryAssessmentBoundaryArchTest.php` — 14 passed (162 assertions).
- `./scripts/dev.sh artisan test --compact tests/Feature/Attendance/ClassSessionBulkUpdateAttendanceTest.php tests/Feature/Architecture/AttendanceDeliveryMigrationArchTest.php tests/Feature/Architecture/CourseDeliveryAssessmentBoundaryArchTest.php` — 9 passed (57 assertions).
- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/AttendanceDeliveryMigrationArchTest.php tests/Feature/Attendance/ClassSessionBulkUpdateAttendanceTest.php tests/Feature/Attendance/StandaloneAttendancePagesTest.php tests/Feature/Lecturer/LecturerAttendanceMarkApiTest.php tests/Feature/CourseOffering/CourseOfferingRecordAttendanceTest.php tests/Feature/Attendance/CourseOfferingAttendanceReportTest.php tests/Feature/Lecturer/LecturerRosterInactiveStudentTest.php` — 33 passed (233 assertions).
- Legacy-caller scan for the retired attendance and class-session controllers/services — no matches.
- `./scripts/dev.sh artisan test --compact` — remains failing in unrelated suites outside this migration; focused coverage and the migration-debt guard pass.

## Notes

- Student and lecturer portal repositories were clean. Their attendance endpoints and response envelopes remain unchanged, so no portal code changes were required.
- Staff attendance-grid reporting and lecturer session summaries now read through Delivery queries using `CourseRosterReader` and `StudentReferenceReader`; `operational_presence_rate` is explicit while `attendance_percentage` remains as a compatibility alias.
- Lecturer course attendee responses now distinguish `operational_presence_rate` from `academic_attendance_rate`; the former remains available as the compatible `attendance_percentage` alias.
- Operational presence now uses expected active attendees (active roster × scheduled sessions), including partially recorded sessions. It is null for inactive roster rows; Academic Attendance Rate retains its separate recorded-session semantics.
- Final spec review found no remaining issue-10 acceptance gap.
- Delivery controller validation, orchestration, reporting, and export logic now live behind Delivery FormRequests, Actions, and Queries. Cross-campus routes retain their 404 behavior, API envelopes remain compatible, and lecturer status normalization is covered by regression tests.
- Final spec and standards reviews found no remaining issue-10 gap or hard violation. The only outstanding verification concern is the unrelated full-suite baseline, which needs maintainer triage before a repository-wide green claim.
