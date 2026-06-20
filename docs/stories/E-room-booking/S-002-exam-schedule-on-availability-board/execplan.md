# Exec Plan - S-002

## Goal

Make scheduled exam-resit blocks visible on the room availability board and exclude their exact time windows from each room's free windows, without changing any write path, route, or schema.

## Scope

In scope:

- Add `exam_room_slots` (status `scheduled` only) as a third occupancy source in `GetRoomAvailabilityBoardQuery::handle()`.
- Map exam slots to the shared board event shape with `type = 'exam_room_slot'` and a unit-derived label.
- De-duplicate exam slots already mirrored as an active room booking (`room_booking_id` in the board's booking set).
- Subtract exam windows from `free_windows` by feeding exam events through the existing concat/group/free-window pipeline.
- Render a distinct exam chip + legend entry on `resources/js/pages/room-bookings/Availability.vue`.
- Keep `availability.summary.busy_events` accurate.

Out of scope:

- Migrations, new columns, new tables.
- New web/API routes or request-contract changes.
- Exam create/edit/cancel from the booking surface.
- Hard booking-time conflict enforcement against exam blocks in the create flow (follow-up backlog item).
- Changes to `ExamScheduleConflictChecker`.
- Student/lecturer portal changes.

## Risk Classification

Risk flags:

- Existing behavior: free-window output for rooms with exams will shrink (intended).
- Read-only: no data model or public contract change.
- Performance: a third per-room/date query source; must avoid N+1 on `sessions.unit`.

Hard gates:

- None. Additive, read-only display change.

Lane:

- normal

## Work Phases

1. Discovery: confirm `ExamRoomSlot` relations, status constants, and the optional `room_booking_id` mirror path; confirm the board's active-booking id set for de-dup.
2. Characterization: add a test asserting current board output does NOT include exam blocks (locks the starting behavior before the change).
3. Backend: extend `GetRoomAvailabilityBoardQuery` with the `examEvents` source, de-dup, summary count, and the shared event shape; eager-load `sessions.unit`.
4. Free-window proof: assert exam windows are removed from `free_windows` while same-day non-exam gaps remain bookable.
5. Frontend: add the exam chip variant + legend on `Availability.vue`; keep it read-only.
6. Verification: targeted backend feature test, Pint on touched PHP, ESLint/Prettier on the touched Vue file, and a build/type check per repo norms; browser smoke screenshot if the local app is reachable.
7. Harness update: record a trace, set story status/evidence, and add a backlog item for booking-time hard conflict enforcement against exam blocks.

## Stop Conditions

Pause for human confirmation if:

- Discovery shows exam blocks are reliably always mirrored to `room_bookings`, making a dedicated exam source redundant (would change the approach).
- Product wants exam-day rooms hidden or marked fully unbookable instead of window-level exclusion.
- Avoiding N+1 requires a schema/index change rather than eager loading.
- The board must additionally hard-block booking creation over exam windows within this slice.
