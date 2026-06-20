# Overview - S-003 Block Bookings Over Exam Slots

## Current Behavior

- S-002 made scheduled exam-resit blocks (`exam_room_slots`) visible on the availability board and subtracted their windows from `free_windows`, but enforcement was explicitly deferred (harness backlog #14).
- The room-booking create/update/preview conflict path still only checks two occupancy sources: active `room_bookings` and non-cancelled/non-postponed `class_sessions`.
- Conflict detection lives in two places:
  - Module path: `RoomBookingSlotValidator::conflictsFor()` → used by `PreviewRoomBookingSeriesAvailabilityQuery` → used by `CreateRoomBookingSeriesAction` (hard block on create) and `apiPreviewSeries` (form preview).
  - Legacy path: `RoomBookingService::checkForConflicts()` (hard block on update), `RoomBookingService::checkAllConflicts()` (`apiCheckConflicts` single-day preview).
- `RoomBookingService::approveBooking()` performs no conflict re-check at all.
- Net effect: staff can create, update, and approve a room booking that overlaps a scheduled exam block. The board warns visually, but nothing blocks the write. This is the display-vs-enforcement gap.
- `ExamScheduleConflictChecker::assertRoomBlockAvailable()` (Academic module) already enforces the inverse direction — a new exam block cannot overlap an active room booking — using the half-open overlap rule `existing.start < new.end AND existing.end > new.start`. The booking side has no symmetric guard.

## Target Behavior

- Creating a room booking that overlaps a `scheduled` exam block in the same room is blocked (the existing series all-or-nothing conflict flow rejects the set).
- Updating a booking into an overlap with a scheduled exam block is blocked.
- Approving a pending booking that now overlaps a scheduled exam block is blocked (an exam can be scheduled after a booking is submitted but before it is approved).
- The create-form conflict preview (`apiPreviewSeries`) and the single-day preview (`apiCheckConflicts`) report exam overlaps as conflicts so staff see them before submit.
- Only `scheduled` exam slots block; `completed`/`cancelled` slots do not (consistent with `ExamScheduleConflictChecker` and the S-002 board).
- A booking being edited/approved is not blocked by an exam slot that merely mirrors that same booking (`exam_room_slots.room_booking_id` equals the excluded booking id).
- The overlap rule is half-open: blocks touching only at a boundary (e.g. 09:00–11:00 then 11:00–13:00) do not conflict.

## Affected Users

- Staff/admins creating or editing room bookings.
- Room managers approving booking requests.

## Portal Impact

none

## Affected Product Docs

- `docs/db-flow.md` (note exam-resit occupancy now participates in booking conflict enforcement, not just display).

## Non-Goals

- No new tables/columns/migrations.
- No new route or request-contract change.
- No change to `ExamScheduleConflictChecker` (the exam-creation side).
- No retroactive sweep of already-approved bookings that overlap exams (enforcement applies at create/update/approve time only).
- No change to booking-vs-booking approve re-validation (that pre-existing gap is out of scope; this story adds exam re-validation at approve only).
- No student/lecturer portal changes.

## Open Product Decisions

- Resolved: hard block (not soft warn) on create/update/approve overlap with scheduled exam blocks.
- Resolved: approve-time re-check is added for exam overlaps specifically, because exam scheduling and booking approval are separate workflows in time.
- Resolved: enforcement reuses the same half-open overlap rule and `scheduled`-only filter as the S-002 board and `ExamScheduleConflictChecker`.
