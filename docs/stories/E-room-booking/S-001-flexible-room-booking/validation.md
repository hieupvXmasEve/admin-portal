# Validation - S-001

## Proof Strategy

The main proof is backend conflict correctness across a generated set, plus UI proof that staff can see and resolve conflicts before submit.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Date-range expansion, weekday selection, odd/even bulk fill if implemented, same-time defaulting, per-occurrence override normalization. |
| Integration | Series creation succeeds when all occurrences are free; rolls back all rows if one occurrence conflicts; detects active room booking overlap; detects class session overlap; ignores cancelled/rejected booking conflicts; clone draft excludes status/approval/action fields. |
| E2E | Create a one-week same-time booking; create a range with per-date time overrides; clone an existing booking into a new date; use availability board filters to find a free room. |
| Platform | Responsive desktop/mobile layout for create planner and availability board. |
| Performance | Availability board query for one building and 7-14 days does not create N+1 room/session queries. |
| Logs/Audit | Series creation logs outcome and creates `room_booking_actions` for each persisted booking. |

## Fixtures

- One campus with at least one building.
- Three rooms in the building: fully free, partially booked, and blocked/unbookable.
- Existing approved room booking on one selected date/time.
- Existing class session on one selected date/time.
- Staff user with `create_room_booking` and `view_room_booking`.
- Room manager user with `approve_room_booking`.

## Commands

```text
./scripts/dev.sh test --filter RoomBooking
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh artisan pint
```

If repo-wide type-check still fails on known baseline drift, capture the filtered diagnostics for touched files and run targeted build/lint proof.

## Acceptance Evidence

- `./scripts/dev.sh artisan route:list --name=room-bookings` passed. All room booking web routes resolve to `App\Modules\Facilities\Http\Web\RoomBookingController`, including `room-bookings.availability` and `api.room-bookings.preview-series`.
- `./scripts/dev.sh test --filter RoomBookingSeriesTest` passed: 4 tests, 15 assertions. Covered grouped multi-day creation, all-or-nothing booking conflict rollback, class session conflict detection, and clone draft workflow-state stripping.
- `./scripts/dev.sh composer exec pint -- app/Modules/Facilities database/factories/RoomFactory.php database/factories/ClassSessionFactory.php tests/Feature/Facilities/RoomBookingSeriesTest.php bootstrap/providers.php routes/web.php` passed and formatted the touched PHP files.
- `./scripts/dev.sh npm exec eslint resources/js/pages/room-bookings/Create.vue resources/js/pages/room-bookings/Availability.vue resources/js/pages/room-bookings/Index.vue resources/js/pages/room-bookings/MyBookings.vue resources/js/pages/room-bookings/Show.vue resources/js/constants/menu-sidebar.ts resources/js/constants/room-booking-routes.ts resources/js/utils/routes.ts` passed.
- `./scripts/dev.sh npm exec prettier --check resources/js/pages/room-bookings/Create.vue resources/js/pages/room-bookings/Availability.vue resources/js/pages/room-bookings/Index.vue resources/js/pages/room-bookings/MyBookings.vue resources/js/pages/room-bookings/Show.vue resources/js/constants/menu-sidebar.ts resources/js/constants/room-booking-routes.ts resources/js/utils/routes.ts` passed.
- `./scripts/dev.sh npm run build` passed after regenerating Ziggy. Vite reported existing chunk-size/plugin timing warnings.
- `git diff --check -- ...booking touched paths...` passed for the booking implementation paths.
- Full `./scripts/dev.sh npm run type-check` failed with Node heap exhaustion under the default memory limit. A 4GB `vue-tsc --noEmit` run completed but still reported repo-wide pre-existing diagnostics outside this slice; filtering that output for touched booking files produced no diagnostics.
- Full `./scripts/dev.sh npm run format:check` still fails on pre-existing repo-wide formatting/syntax drift outside this slice. Targeted Prettier check for touched files passed.
