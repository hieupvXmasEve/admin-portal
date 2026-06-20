# Exec Plan - S-003

## Goal

Close the S-002 display-vs-enforcement gap: hard-block creating, updating, or approving a room booking that overlaps a scheduled exam-resit block, reusing the half-open overlap rule and `scheduled`-only filter. No migration, route, or contract change.

## Scope

In scope:

- New `ExamSlotBookingConflictChecker` (Facilities Support): a read-only predicate returning exam-overlap conflict rows for a room/date/window, with optional booking-mirror exclusion.
- Wire it into `RoomBookingSlotValidator::conflictsFor()` (module create/preview path).
- Wire it into `RoomBookingService::checkForConflicts()` (update hard block), `checkAllConflicts()` (single-day preview API), and `approveBooking()` (approve hard block).
- Catch `InvalidArgumentException` in the controller `approve()` so a blocked approval surfaces as a flash error.

Out of scope:

- Migrations, new tables/columns.
- New web/API routes or request-contract changes.
- Changes to `ExamScheduleConflictChecker`.
- Retroactive enforcement against already-approved overlapping bookings.
- Booking-vs-booking approve re-validation (pre-existing gap).
- Student/lecturer portal changes.

## Risk Classification

Risk flags:

- Existing behavior: bookings that previously could be created/updated/approved over an exam are now blocked (intended).
- Read-only on exam data; no data model or public contract change.
- Two conflict checkers (module + legacy) must stay consistent — mitigated by a single shared predicate.

Hard gates:

- None. No schema or contract change.

Lane:

- normal

## Work Phases

1. Discovery: confirm the two conflict paths and the approve path (done — module `RoomBookingSlotValidator::conflictsFor` + legacy `RoomBookingService` create/update/approve/preview).
2. TDD RED: feature test asserting create-series, update, and approve all block on a scheduled exam overlap; preview reports it; completed/cancelled slots and boundary-touching do not block; a booking's own mirror does not block its approval.
3. Build the shared `ExamSlotBookingConflictChecker`.
4. Wire into module slot validator (create/preview).
5. Wire into legacy service (update/preview/approve) + controller approve catch.
6. GREEN: new test + `RoomBookingSeriesTest` regression + `RoomAvailabilityExamBlockTest` (S-002) regression.
7. Verify: Pint on touched PHP; targeted checks. Update harness story/backlog #14/trace and `validation.md`.

## Stop Conditions

Pause for human confirmation if:

- Discovery shows the create page no longer uses the series preview (would change the wiring point).
- Product wants approve-time enforcement extended to booking-vs-booking (broader behavior change).
- Avoiding a duplicate conflict row for a mirrored exam slot requires schema/relationship changes rather than a simple `excludeBookingId` skip.
- Adding the controller `approve()` catch conflicts with an existing global exception-handling convention.
