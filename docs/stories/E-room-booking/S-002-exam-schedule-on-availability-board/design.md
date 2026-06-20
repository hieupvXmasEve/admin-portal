# Design - S-002

## Domain Model

Primary domain concept: a scheduled exam-resit block (`exam_room_slot`) is room occupancy that must appear on the room availability board and reduce that room's free windows.

Invariants:

- Only `exam_room_slots` with `status = ExamRoomSlot::STATUS_SCHEDULED` occupy a room. `completed` and `cancelled` slots are ignored (matches `ExamScheduleConflictChecker`).
- An exam block occupies exactly `[start_time, end_time)` on `exam_date` for `room_id`, using the same half-open overlap semantics already used across the board (`event.end_time > windowStart AND event.start_time < windowEnd`).
- An exam block subtracts only its own time range from `free_windows`; the room remains bookable in the remaining gaps.
- An exam block whose `room_booking_id` references a room booking already present in the board's active-booking set must NOT be emitted as a separate exam event (no double display). The canonical booking event already represents that occupancy.
- The board read path is non-mutating; it must not write to or alter exam-resit data.

## Application Flow

### Queries

- `GetRoomAvailabilityBoardQuery::handle()` gains a third event source: `examEvents`, built from `ExamRoomSlot`.
  - Filter: `whereIn('room_id', $roomIds)`, `whereBetween('exam_date', [$startDate, $endDate])`, `where('status', ExamRoomSlot::STATUS_SCHEDULED)`.
  - Eager load `sessions.unit` (and only those) to build the human label without N+1; do not load invigilators for the board.
  - Map each slot to the shared event shape with `type => 'exam_room_slot'`:
    - `id`, `exam_room_slot_id`, `room_id`, `date` (from `exam_date`), `start_time`, `end_time` (formatted via existing `formatTime()`), `status`.
    - `title`: derived from the slot's sessions — e.g. joined unit codes, or `"Thi lại"` plus unit code/name when a single session, falling back to `"Exam"` when no session rows exist.
    - `session_count`: number of unit sessions sharing the block (Academic groups small retakes).
    - `is_editable => false` (read-only, like class sessions).
  - De-duplicate: collect the set of active `room_booking` ids already emitted into `bookingEvents`; skip any exam slot whose `room_booking_id` is in that set.
- `examEvents` are concatenated with `bookingEvents` and `classEvents` before the existing `->filter(...)->groupBy(room_id|date)` step, so the existing free-window subtraction (`freeWindows()`) automatically excludes exam windows with no change to that method.

### Commands

- None. This story adds no write path.

## Interface Contract

Existing surfaces unchanged:

- Route `room-bookings.availability` (`GET /room-bookings/availability`) and `ListAvailabilityRequest` are unchanged.
- The Inertia component remains `room-bookings/Availability`.

Payload change (additive only):

- Each entry in `availability.rooms[].days[].events[]` may now include objects with `type: 'exam_room_slot'`.
- Existing consumers that switch on `event.type` keep working; only a new branch is added.
- `availability.summary.busy_events` continues to count occupancy; add the exam event count to it so the summary stays accurate.

Event shape (exam):

```text
type             = 'exam_room_slot'
id               number
exam_room_slot_id number
room_id          number
date             'Y-m-d'
title            string   // exam label from unit session(s)
start_time       'H:i'
end_time         'H:i'
status           'scheduled'
session_count    number
is_editable      false
```

## Data Model

- No migration. Read-only use of existing tables: `exam_room_slots`, `exam_resit_sessions`, `units`.
- Use existing relations: `ExamRoomSlot::room()`, `ExamRoomSlot::sessions()`, `ExamResitSession::unit()`.
- Reuse the existing `exam_room_slot_conflict_idx` / `exam_room_slot_scope_idx` indexes for the room/date/status filter.

## UI / Platform Impact

`resources/js/pages/room-bookings/Availability.vue`:

- Add a third visual treatment for `type === 'exam_room_slot'` busy chips, distinct from `room_booking` and `class_session` (color/badge), labeled as exam (e.g. "Thi lại").
- Show the exam title, time range, and `session_count` when greater than one ("n môn").
- Keep the chip read-only (no edit/book affordance), consistent with class-session chips.
- Update the board legend/key to include the exam source.
- Free windows render unchanged; they will naturally exclude exam windows because the backend subtracts them.

## Observability

- No new logs. The board is a read surface.
- Exam-resit lifecycle logging remains owned by the exam-resit actions/models (`ExamRoomSlot` is an `AuditableModel`).

## Alternatives Considered

1. Hide a room for the whole day when it has any exam (`is_bookable = false` for that date). Rejected by product decision: only the exam window is excluded; the room stays bookable in other gaps.
2. Remove rooms with exam blocks from the board entirely. Rejected: it contradicts the requirement to display exam schedules and hides legitimate free time.
3. Rely on `exam_room_slots.room_booking_id` mirroring so exams show up as ordinary bookings. Rejected: mirroring is optional/nullable, so exams without a backing booking would stay invisible; this story must show all scheduled exam blocks.
4. Add a separate exam API endpoint and fetch client-side. Rejected for the first slice: the board is server-rendered via Inertia, and an additive prop keeps one round trip and one source of truth.
