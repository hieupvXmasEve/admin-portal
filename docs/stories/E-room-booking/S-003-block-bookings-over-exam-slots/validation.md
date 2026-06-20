# Validation - S-003

## Proof Strategy

Backend proof that the three write surfaces (create-series, update, approve) hard-block on a scheduled exam overlap, the preview surfaces report it, and the exclusions (status, boundary-touching, own-mirror) behave correctly. Reuses existing room-booking test fixtures plus an exam slot.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | `ExamSlotBookingConflictChecker::conflictsFor()`: detects scheduled overlap; ignores completed/cancelled; ignores boundary-touching; skips own-mirror via excludeBookingId; builds title from `sessions.unit`. |
| Integration | Create series throws `RoomBookingSeriesConflictException` when an occurrence overlaps a scheduled exam block and creates no rows; update throws on overlap; approve throws/blocks when a pending booking overlaps a scheduled exam scheduled after submission; preview (`PreviewRoomBookingSeriesAvailabilityQuery`) marks the occurrence unavailable; `checkAllConflicts()` returns an `exam_room_slot` conflict; completed/cancelled exam slot does not block; approving a booking whose own mirror is the only "overlap" succeeds. |
| E2E | Create-form preview shows an exam conflict and blocks submit for an exam-occupied window. |
| Platform | n/a (no UI change beyond existing conflict rendering). |
| Performance | Conflict checker adds one indexed query per occurrence (uses `exam_room_slot_conflict_idx`); no N+1 beyond eager-loaded `sessions.unit`. |
| Logs/Audit | Read predicate writes nothing; approve denial does not corrupt state (no partial update). |

## Fixtures

- One campus, one building, one room (07:00–20:00).
- A `scheduled` `exam_room_slot` 09:00–11:00 on a target date with a linked `exam_resit_session` → `unit`.
- A `completed` and a `cancelled` exam slot for negative cases.
- A pending `RoomBooking` for the approve case.
- A booking + an exam slot mirroring it (`room_booking_id`) for the own-mirror exclusion case.
- Staff user with create/approve capability.

## Commands

```text
./scripts/dev.sh test --filter RoomBookingExamConflict
./scripts/dev.sh test --filter RoomBookingSeriesTest
./scripts/dev.sh test --filter RoomAvailabilityExamBlockTest
./scripts/dev.sh artisan pint
```

If repo-wide type-check/format-check still fail on known baseline drift, capture filtered diagnostics for touched files.

## Acceptance Evidence

- `tests/Feature/Facilities/RoomBookingExamConflictTest.php` passed: 8 tests / 16 assertions. Covered: checker detects a scheduled overlap and builds a unit-coded title; ignores completed/cancelled/boundary-touching slots; create-series throws `RoomBookingSeriesConflictException` (0 rows) with an `exam_room_slot` conflict; series preview marks the occurrence unavailable; legacy `checkAllConflicts()` returns an `exam_room_slot` conflict; `updateBooking()` throws on overlap; `approveBooking()` throws and leaves the booking pending; approving a booking whose only overlap is its own mirror slot still succeeds.
- Full `tests/Feature/Facilities` suite passed: 16 tests / 44 assertions — `RoomBookingSeriesTest` (S-001) and `RoomAvailabilityExamBlockTest` (S-002) regressions intact.
- Pint passed on the 5 touched PHP files (checker, slot validator, legacy service, controller, test).
- No migration / route / contract change.
- Pre-existing `getClassSessionsForCalendar()` parameter-order deprecation warnings are unrelated to this change.

## Implementation Notes

- New `App\Modules\Facilities\Support\ExamSlotBookingConflictChecker` — single read-only predicate (`conflictsFor()` / `hasConflict()`) returning `exam_room_slot` conflict rows; half-open overlap, `scheduled`-only, optional own-mirror exclusion via `excludeBookingId`.
- Wired into: `RoomBookingSlotValidator::conflictsFor()` (module create/preview), `RoomBookingService::checkAllConflicts()` (single-day preview API), `RoomBookingService::checkForConflicts()` (update hard block), and `RoomBookingService::approveBooking()` (approve hard block, excludes the booking's own mirror).
- `RoomBookingController::approve()` now catches `InvalidArgumentException` and flashes the error instead of 500-ing.

## Closes

- Harness backlog #14 (Hard-block room bookings overlapping scheduled exam slots).
