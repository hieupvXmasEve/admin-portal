# Migrate Course Offering setup and roster operations

Status: ready-for-agent

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Move Course Offering setup, instructor assignment, enrollment, registration, roster, and cockpit operations into Course Delivery & Assessment ownership. The complete staff workflow must consume Registry, Catalog, Workforce, and Facilities answers through accepted boundaries.

## Acceptance criteria

- [ ] Offering setup, assignment, enrollment, registration, and roster operations have one supported owner path.
- [ ] Student, Faculty, Catalog, Academic Period, and Space data enter through neutral references or owner contracts.
- [ ] Course Offering Cockpit routes, permissions, forms, filters, roster behavior, and audit outcomes remain compatible.
- [ ] Completion/statistics compatibility paths are not extended and have explicit downstream retirement ownership.
- [ ] Characterization and architecture tests prove the cutover before legacy callers are retired.

## Dependencies resolved

- [Migrate Student Registry identity and Guardian relationships](06-migrate-student-registry-identity-guardians.md)
- [Migrate Academic Catalog & Calendar management](08-migrate-academic-catalog-calendar.md)
- [Migrate Facilities room and booking operations](14-migrate-facilities-room-booking.md)

## Comments

- 2026-07-22: Completed a safe partial cutover for existing staff roster writes. The preserved routes and names now dispatch to `CourseOfferingRosterController` in Academic Delivery: remove a roster member, move a member between sections, bulk-update registration status, and bulk-assign instructors. URLs, permissions, request shapes, and response envelopes were preserved.
- 2026-07-22: Delivery now gets student identity through `StudentReferenceReader` and delegates course-offering attempt moves/removals to Progression through `CourseOfferingAttemptWriter`. `CourseOffering` and `CourseRegistration` are recorded as Delivery-owned models; `AcademicRecord` is recorded as Progression-owned.
- 2026-07-22: The existing staff roster-removal behavior permanently removes matching active and soft-deleted attempt records. It is characterized at action and HTTP levels. No data migration, backfill, cleanup, or portal API contract change was run; the student and lecturer portals were left untouched.
- 2026-07-22: This issue remains `ready-for-agent`: offering setup/cockpit, registration discovery and bulk enrollment, room-related behavior, and their dependency cutovers are still blocked by issues 06, 08, and 14. Completion/statistics compatibility remains in its legacy path and must not be extended; Course Delivery & Assessment owns its retirement once its remaining cockpit callers have a module query/action replacement and corresponding characterization coverage.
- 2026-07-22: The staff `course-offerings.destroy` route now dispatches through a Delivery action and module controller. It preserves the route name, URL, permission, cross-campus 404 behavior, deletion constraints, redirect, and flash message. The remaining bulk-delete path is intentionally unchanged because its per-unit error output requires the Catalog dependency cutover.
- 2026-07-24: Issues 06, 08, and 14 are completed, so they no longer block this issue. The staff `course-offerings.duplicate` route now dispatches through a Delivery action and a Catalog contract instead of directly reading `SyllabusTemplate`. It preserves its URL, route name, permission, cross-campus 404 behavior, section-code behavior, Canvas-template rejection, redirect, and flash behavior. The page no longer emits a manual success toast; native Inertia flash handles both outcomes.
- 2026-07-24: The staff `api.course-offerings.bulk-delete` route now dispatches through Delivery. It validates IDs with an owner FormRequest, preserves campus scoping and roster removal, and obtains Unit codes for blocked-delete messages through the Catalog contract.

## Verification

- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php tests/Feature/CourseOffering/InstructorAssignmentTest.php --compact`
- `./scripts/dev.sh test tests/Feature/Architecture/CourseDeliveryAssessmentBoundaryArchTest.php tests/Feature/Architecture/TeachingEligibilityAssignmentBoundaryTest.php tests/Feature/Architecture/CourseOfferingCatalogBoundaryArchTest.php tests/Feature/Architecture/CourseRosterDeliveryBoundaryArchTest.php --compact`
- `./scripts/dev.sh test tests/Feature/Architecture/MigrationDebtInventoryTest.php --compact`
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table`
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php --compact` — passed: 17 tests, 58 assertions.
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseOfferingDuplicationActionTest.php tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php tests/Feature/CourseOffering/CanvasSyllabusTemplateGuardTest.php --compact` — passed: 15 tests, 73 assertions.
- `./scripts/dev.sh test tests/Feature/Architecture/CourseRosterDeliveryBoundaryArchTest.php tests/Feature/Architecture/CourseOfferingCatalogBoundaryArchTest.php --compact` — passed: 4 tests, 5 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed.
- `./scripts/dev.sh npm exec eslint resources/js/pages/course-offerings/Index.vue` and `./scripts/dev.sh npm exec prettier --check resources/js/pages/course-offerings/Index.vue` — passed.
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php --compact` — passed: 13 tests, 38 assertions.
