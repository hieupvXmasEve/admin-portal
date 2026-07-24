# Migrate class sessions and attendance operations

Status: ready-for-agent

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut class-session scheduling, attendance recording, readiness, reporting, and student/lecturer attendance contracts over to Course Delivery & Assessment while preserving roster rules and the distinction between Academic Attendance Rate and Operational Presence Rate.

## Acceptance criteria

- [x] Staff and lecturer session/attendance mutations use one Course Delivery owner path.
- [x] Student and lecturer attendance reads preserve actor authorization, roster scope, status semantics, and response envelopes.
- [ ] Attendance readiness and reporting use the glossary-defined metrics without frontend inference.
- [x] Existing routes, UI behavior, filters, exports, and notifications remain compatible.
- [ ] Legacy attendance services/controllers/routes have zero supported callers before retirement.

## Blocked by

- [Migrate Course Offering setup and roster operations](09-migrate-course-offering-setup-roster.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/AttendanceDeliveryMigrationArchTest.php tests/Feature/Architecture/CourseDeliveryAssessmentBoundaryArchTest.php tests/Feature/Attendance/ClassSessionBulkUpdateAttendanceTest.php tests/Feature/Attendance/StandaloneAttendancePagesTest.php tests/Feature/Lecturer/LecturerAttendanceMarkApiTest.php tests/Feature/CourseOffering/CourseOfferingRecordAttendanceTest.php` — 27 passed (111 assertions).
- `./scripts/dev.sh npm run type-check` — passed.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed.
- `./scripts/dev.sh artisan test --compact` — exited 2 with failures outside this issue's changed surface; focused migration coverage passes.

## Notes

- Student and lecturer portal repositories were clean. Their attendance endpoints and response envelopes remain unchanged, so no portal code changes were required.
- Review found that the combined course-statistics and lecturer-course services still own attendance reporting/readiness while also reading Student Registry and Progression data. Moving them directly into Delivery violates the migration-debt cross-context boundary; extract Delivery-owned readers behind contracts before retiring those mixed-domain legacy owners.
