# Own Building, Room, and Space Availability in Facilities

Status: completed

Portal impact: none

## Parent

[Swinx Domain Boundary Decomposition](../PRD.md)

## What to build

Make Facilities the owner of Building, Room, capacity, Space Availability, reservations, and physical conflicts while preserving existing staff administration and booking behavior. Course Delivery and exam scheduling submit slot requirements through a Facilities contract; Facilities must no longer import Academic scheduling services.

## Acceptance criteria

- [x] Existing Building and Room list/create/edit/delete behavior is served by Facilities ownership with unchanged staff URLs and authorization.
- [x] Course and exam scheduling can query Space Availability and create reservations through a Facilities-owned contract.
- [x] Capacity, overlap, campus, and booking-series rules continue to produce the same observable decisions.
- [x] Facilities has no dependency on Academic conflict-checking services or Academic persistence internals.
- [x] Delivery supplies scheduling intent and references without transferring timetable ownership to Facilities.
- [x] Existing room availability, exam conflict, booking-series, and administration tests pass.
- [x] Architecture tests enforce the Facilities/Delivery direction.

## Blocked by

- [Issue 02: Own Institution Campus and Department through the Institution admin flow](02-own-campus-and-department-through-institution.md)
- [Issue 04: Create Course Offerings from Catalog-owned curriculum and period references](04-create-offerings-from-catalog-references.md)

## Verification

- `./scripts/dev.sh artisan test --compact tests/Feature/Architecture/FacilitiesDeliveryBoundaryArchTest.php tests/Feature/Facilities/SpaceReservationContractTest.php tests/Feature/Facilities/RoomAvailabilityExamBlockTest.php tests/Feature/Facilities/RoomBookingExamConflictTest.php tests/Feature/Facilities/RoomBookingSeriesTest.php tests/Feature/Academic/ExamResit/CreateExamRoomSlotActionTest.php` — 35 passed, 81 assertions.
- `./scripts/dev.sh test`, `./scripts/dev.sh npm run type-check`, `./scripts/dev.sh npm run lint`, and `./scripts/dev.sh npm run format:check` completed successfully.
- Two-axis implementation review passed after correcting supplied-reservation reuse and Delivery's direct Room read.
