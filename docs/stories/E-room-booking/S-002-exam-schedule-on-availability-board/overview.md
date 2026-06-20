# Overview - S-002 Exam Schedule on Availability Board

## Current Behavior

- The availability board at `/room-bookings/availability` is rendered by `RoomBookingController::availability()` and powered by `App\Modules\Facilities\Queries\GetRoomAvailabilityBoardQuery::handle()`.
- The board merges exactly two occupancy sources per room/date: active `room_bookings` (`activeBookings()` scope) and non-cancelled/non-postponed `class_sessions`.
- Those events are displayed as busy chips AND subtracted from each room's `free_windows` via `freeWindows()`.
- Exam-resit scheduling lives in a separate model family (`exam_room_slots` + `exam_resit_sessions` + `exam_room_slot_invigilators`), introduced in `2026_06_20_120000_create_exam_resit_scheduling_tables`.
- `ExamScheduleConflictChecker::assertRoomBlockAvailable()` already treats a `scheduled` `exam_room_slot` as room occupancy when Academic schedules an exam block, using the half-open overlap rule `existing.start < new.end AND existing.end > new.start`.
- BUT the availability board ignores `exam_room_slots` entirely. A room that is fully reserved for a retake exam can still appear as free on the board, so staff can attempt to book over a live exam block.
- An `exam_room_slot` may optionally carry a `room_booking_id` (Academic can mirror the exam into the canonical booking ledger). When present, that exam already surfaces on the board as a `room_booking` event; when absent, the exam is invisible to the board.

## Target Behavior

- The availability board shows scheduled exam-resit blocks as a third, visually distinct occupancy source alongside room bookings and class sessions.
- Each exam block is rendered as a busy event with a clear label (exam unit code/name, time range, status) and is `is_editable: false`, like class sessions.
- Each scheduled exam block is subtracted from the affected room's `free_windows` for the exact exam time range only. The room stays bookable in the remaining free gaps of the same day (resolved product decision; see Open Product Decisions).
- A room with an exam block is NOT hidden and is NOT marked unbookable for the whole day; only the exam time window is excluded from availability.
- Exam blocks already mirrored into the booking ledger (`exam_room_slots.room_booking_id` pointing at an active room booking already on the board) are not double-displayed.

## Affected Users

- Staff and admins who use the availability board to find free rooms and create bookings.
- Room managers who inspect occupancy before approving a booking request.
- Academic operations staff who schedule retake exams and need exam blocks visible to the booking surface.

## Portal Impact

none

## Affected Product Docs

- `docs/db-flow.md` (note the exam-resit occupancy source now feeds the availability board read path).
- No persisted contract change; this story adds a read-only display source, so no migration or write-path doc change is required.

## Non-Goals

- No student or lecturer portal changes.
- No new database tables, columns, or migrations.
- No new web or API route; the change is an additive prop inside the existing `availability` Inertia payload.
- No exam-block create/edit/cancel workflow on the booking surface; the board only reads and displays exam blocks.
- No hard booking-conflict enforcement against exam blocks in the create flow in this slice (display + free-window exclusion only). Booking-time hard blocking is tracked as a follow-up.
- No change to `ExamScheduleConflictChecker` behavior; it remains the source of truth for exam-side scheduling.

## Open Product Decisions

- Resolved: exam blocks subtract only their exact time window from `free_windows`; the room remains bookable in the remaining gaps of the same day. The room is not hidden and the day is not marked fully unbookable.
- Resolved: exam blocks are display-only on this surface in this slice; the create-form conflict path is not changed here.
- Resolved: only `scheduled` exam slots count as occupancy; `completed` and `cancelled` slots are excluded, consistent with `ExamScheduleConflictChecker`.
- Resolved: exam slots backed by an already-listed active room booking (`room_booking_id`) are de-duplicated so the same block is not shown twice.
