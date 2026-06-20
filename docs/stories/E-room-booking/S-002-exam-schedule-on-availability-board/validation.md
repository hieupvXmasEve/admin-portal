# Validation - S-002

## Proof Strategy

The main proof is backend: scheduled exam blocks appear in the board's per-room/per-date events, are de-duplicated against mirrored bookings, and are subtracted from `free_windows` for the exact exam time range while same-day non-exam gaps stay bookable. Secondary proof is UI rendering of a distinct, read-only exam chip.

## Test Plan

| Layer | Cases |
| --- | --- |
| Unit | Exam slot -> board event mapping (title from `sessions.unit`, `session_count`, `is_editable=false`, formatted times); only `scheduled` slots mapped; de-dup skips slots whose `room_booking_id` is in the active-booking set. |
| Integration | Board includes a `type=exam_room_slot` event for a scheduled slot in range; excludes `completed`/`cancelled` slots; exam window removed from `free_windows` while other same-day gaps remain; room with an exam stays `is_bookable=true` for the day; a slot mirrored to an active room booking appears once, not twice; `summary.busy_events` includes exam count. |
| E2E | Open `/room-bookings/availability` for a date range containing a retake exam; confirm the exam chip renders with its label and the room shows reduced free windows but remains bookable in the remaining gap. |
| Platform | Exam chip readable on desktop/mobile; legend shows the exam source. |
| Performance | Board query for one building over 7-14 days adds no N+1 on `exam_room_slots` / `sessions.unit` (eager-loaded). |
| Logs/Audit | No new logs expected; assert the read path performs no writes to exam-resit tables. |

## Fixtures

- One campus with one building and at least two rooms.
- Room R1: a `scheduled` `exam_room_slot` on a date in range (e.g. 09:00-11:00) with one or more `exam_resit_sessions` linked to a `unit`.
- Room R1: an existing free gap the same day (e.g. 07:00-09:00 and 11:00-20:00) to prove partial bookability.
- Room R2: a `cancelled` and a `completed` exam slot (must NOT appear).
- One exam slot whose `room_booking_id` references an active room booking already on the board (de-dup case).
- Staff user with `view_room_booking`.

## Commands

```text
./scripts/dev.sh test --filter Availability
./scripts/dev.sh test --filter RoomBooking
./scripts/dev.sh npm run type-check
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format:check
./scripts/dev.sh artisan pint
```

If repo-wide `type-check` / `format:check` still fail on known baseline drift (see project memory), capture filtered diagnostics for touched files and run targeted build/lint proof instead.

## Acceptance Evidence

- `tests/Feature/Facilities/RoomAvailabilityExamBlockTest.php` passed: 4 tests / 13 assertions. Covered: scheduled exam slot emitted as a `type=exam_room_slot` event (correct times, `is_editable=false`, unit-code title); exam window subtracted from `free_windows` while same-day gaps (`07:00-09:00`, `11:00-20:00`) stay free and the room stays `is_bookable=true`; `cancelled`/`completed` slots excluded; a slot whose `room_booking_id` references an active room booking is shown once (no double-display).
- Regression `tests/Feature/Facilities/RoomBookingSeriesTest.php` passed: 4 tests / 15 assertions (no behavior change to existing booking series/clone/conflict logic).
- Pint passed on `app/Modules/Facilities/Queries/GetRoomAvailabilityBoardQuery.php` and the new test file (2 files).
- ESLint and Prettier passed on `resources/js/pages/room-bookings/Availability.vue`.
- `pnpm run build` passed (exit 0; only the pre-existing chunk-size warning).
- Whole-project `vue-tsc` was not run: it OOMs in the dev container on the known repo baseline; ESLint is the per-file substitute and passed.
- Note: the first `migrate:fresh` hit the pre-existing `canvas_integrations` FK ordering flake (unrelated to this change); the suite passed on retry against the migrated DB.

## Implementation Notes

- Backend: `GetRoomAvailabilityBoardQuery::handle()` now builds a third `examEvents` source from `ExamRoomSlot` (status `scheduled` only), eager-loading `sessions.unit`, de-duplicating slots whose `room_booking_id` is already in the active-booking set, and concatenating exam events into the existing concat/group/free-window pipeline. `summary.busy_events` includes the exam count. Added a private `examTitle()` helper ("Thi lại: <unit codes>").
- Frontend: `Availability.vue` renders exam chips with an amber treatment (distinct from booking/class), an `Exam` badge, a `· n môn` suffix when a slot groups multiple unit sessions, and a board legend (Free / Booking / Class / Exam).

## Deferred / Backlog

- Hard booking-time conflict enforcement: block (or warn on) room booking creation/approval that overlaps a scheduled exam block, mirroring `ExamScheduleConflictChecker` on the booking side. Tracked as harness backlog #14; this story is display + free-window exclusion only.
