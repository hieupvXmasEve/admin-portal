# Migrate Course Offering setup and roster operations

Status: ready-for-human

Portal impact: both

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Move Course Offering setup, instructor assignment, enrollment, registration, roster, and cockpit operations into Course Delivery & Assessment ownership. The complete staff workflow must consume Registry, Catalog, Workforce, and Facilities answers through accepted boundaries.

## Acceptance criteria

- [x] Offering setup, assignment, enrollment, registration, and roster operations have one supported owner path.
- [x] Student, Faculty, Catalog, Academic Period, and Space data enter through neutral references or owner contracts.
- [x] Course Offering Cockpit routes, permissions, forms, filters, roster behavior, and audit outcomes remain compatible.
- [x] Completion/statistics compatibility paths are not extended and have explicit downstream retirement ownership.
- [x] Characterization and architecture tests prove the cutover before legacy callers are retired.

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
- 2026-07-24: Cut the staff `api.course-offerings.change-room` route over to a Delivery action and module controller. The route name, URL, permission, redirect, flash messages, and cross-campus 404 are preserved. Delivery validates each non-cancelled class-session slot through the Facilities `SpaceReferenceReader` and `SpaceAvailabilityReader` contracts before atomically updating the offering's sessions. The issue remains `ready-for-agent`: cockpit reads plus registration discovery/bulk enrollment still require their Registry and Catalog boundary cutovers.
- 2026-07-24: Registration discovery and bulk enrollment now dispatch through Delivery actions and a module controller. Student identity/status/program facts are supplied by the Student Registry reference, Unit facts by the Catalog contract, and actual inserts use the existing Delivery enrollment action. Instructor-assignment readiness checks now use a Delivery query with Academic Period and Unit references. The cockpit show/index page adapter is the remaining Course Offering route cutover.
- 2026-07-24: Completed the cockpit cutover. `course-offerings.index` and `course-offerings.show` now belong to the Academic module's `CourseOfferingCockpitController`; the Delivery cockpit queries preserve filters, pagination, statistics, nested unit/module data, roster/session eager props, room availability, siblings, survey forms, operational state, and deferred scores/survey groups. The legacy route file no longer owns either cockpit route. Completion and statistics compatibility routes remain intentionally unchanged for their downstream retirement stories.
- 2026-07-24: Downstream retirement ownership is explicit: Course Delivery & Assessment owns retirement of the completion compatibility path after the remaining assessment/result callers are migrated; the course-statistics compatibility path remains a redirect-only surface until the cockpit scores/assessment retirement work in issue 11 is complete.

## Verification

- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php tests/Feature/CourseOffering/InstructorAssignmentTest.php --compact`
- `./scripts/dev.sh test tests/Feature/Architecture/CourseDeliveryAssessmentBoundaryArchTest.php tests/Feature/Architecture/TeachingEligibilityAssignmentBoundaryTest.php tests/Feature/Architecture/CourseOfferingCatalogBoundaryArchTest.php tests/Feature/Architecture/CourseRosterDeliveryBoundaryArchTest.php --compact`
- `./scripts/dev.sh test tests/Feature/Architecture/MigrationDebtInventoryTest.php --compact`
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table`
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php --compact` — passed: 17 tests, 58 assertions.
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseOfferingDuplicationActionTest.php tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php tests/Feature/CourseOffering/CanvasSyllabusTemplateGuardTest.php --compact` — passed: 15 tests, 73 assertions.
- `./scripts/dev.sh test tests/Feature/Architecture/CourseRosterDeliveryBoundaryArchTest.php tests/Feature/Architecture/CourseOfferingCatalogBoundaryArchTest.php --compact` — passed: 4 tests, 5 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed.
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php --compact` — passed: 16 tests, 47 assertions.
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php tests/Feature/Architecture/CourseOfferingCatalogBoundaryArchTest.php tests/Feature/Architecture/TeachingEligibilityAssignmentBoundaryTest.php --compact` — passed: 16 tests, 47 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed after the registration and readiness cutover.
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseOfferingIndexTest.php tests/Feature/CourseOffering/CourseOfferingOperationalStateTest.php tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php --compact` — passed: 28 tests, 293 assertions (cockpit route ownership, filters, statistics, and operational state).
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed after the cockpit cutover (all rules at or below baseline).
- `./scripts/dev.sh npm exec eslint resources/js/pages/course-offerings/Index.vue` and `./scripts/dev.sh npm exec prettier --check resources/js/pages/course-offerings/Index.vue` — passed.
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php --compact` — passed: 13 tests, 38 assertions.
- `./scripts/dev.sh test tests/Feature/CourseOffering/CourseRosterDeliveryActionTest.php tests/Feature/CourseOffering/CourseOfferingRosterRouteCutoverTest.php tests/Feature/Architecture/FacilitiesDeliveryBoundaryArchTest.php tests/Feature/Architecture/CourseRosterDeliveryBoundaryArchTest.php --compact` — passed: 32 tests, 103 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed.
