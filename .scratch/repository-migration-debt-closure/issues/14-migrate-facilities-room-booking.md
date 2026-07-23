# Migrate Facilities room and booking operations

Status: completed

Portal impact: lecturer

## Parent

[Close repository-wide Migration Debt](01-close-repository-wide-migration-debt.md)

## What to build

Cut Building, Room, Space Availability, reservation, and booking-conflict workflows over to Facilities ownership. Academic scheduling and exams provide timetable intent through contracts, while Facilities remains the sole owner of physical availability and reservations.

## Acceptance criteria

- [x] Building, Room, availability, booking, recurring reservation, and conflict behavior use one Facilities-owned path.
- [x] Academic and exam consumers query/reserve space through approved contracts without importing Facilities implementation.
- [x] Staff routes, permissions, filters, calendar behavior, validation, and conflict messages remain compatible.
- [x] Existing booking and exam-conflict regression suites pass across owner boundaries.
- [x] Legacy room/booking services, controllers, and routes have zero supported callers before retirement.

## Blocked by

- [Migrate Institution & Organization reference management](05-migrate-institution-organization-references.md)
- [Migrate Academic Catalog & Calendar management](08-migrate-academic-catalog-calendar.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/FacilitiesDeliveryBoundaryArchTest.php` — 4 passed, 19 assertions.
- `./scripts/dev.sh artisan test --compact tests/Feature/Facilities/RoomBookingExamConflictTest.php tests/Feature/Facilities/RoomBookingSeriesTest.php tests/Feature/Facilities/RoomAvailabilityExamBlockTest.php tests/Feature/Facilities/SpaceReservationContractTest.php` — 18 passed, 50 assertions.
- `./scripts/dev.sh artisan migration-debt:inventory --check --format=table` — passed; the frozen service, controller, and route counts decreased, with no guard regression.
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` and `git diff --check` — passed.
- `pnpm exec eslint resources/js/pages/room-bookings/Calendar.vue` and `pnpm exec prettier --check resources/js/pages/room-bookings/Calendar.vue` — passed. `Calendar.vue` is the only frontend file changed by this issue; no component-level unit or E2E test is registered for it.

## Completion notes

- Supported staff entry points are now `rooms.*`, `room-bookings.*`, and `api.admin.rooms.api-index`, all served from Facilities routes/controllers. The legacy room/booking services, controllers, request classes, and route fragments were retired after their supported callers reached zero.
- Academic provides timetable occupancy through `AcademicSpaceOccupancyReader`; exam space reservation continues through the Facilities contracts. The former Academic room-block conflict bypass was removed.
- Calendar output preserves class-session room, description, instructor, and course-offering fields, and now renders scheduled exam reservations as non-editable calendar items.
- Portal impact is lecturer, but no lecturer API request or response contract changed, so no `FE/lecturer-nuxt` change was required.
- No migration or data backfill ran. Roll back by reverting this change set.
