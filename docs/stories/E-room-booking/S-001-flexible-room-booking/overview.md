# Overview - S-001 Flexible Room Booking

## Current Behavior

- Staff can create one `room_bookings` record for one room, one date, and one time range from `/room-bookings/create`.
- The create page performs a single-day conflict preview through `/api/room-bookings/check-conflicts`.
- Conflict checks include active room bookings and non-cancelled/non-postponed `class_sessions`.
- The database already has recurrence-related columns (`is_recurring`, `recurrence_type`, `recurrence_end_date`, `recurrence_days`, `parent_booking_id`), but the current create/edit UI and service path do not expose a practical multi-day workflow.
- The calendar page shows occupied dates and sessions, but there is no room availability board that summarizes free/busy windows by room, building, and time.
- There is no clone workflow; staff must manually retype data for similar bookings.

## Target Behavior

- Staff can create a booking plan from date X to date Y, generate one booking occurrence per selected date, and use the same time range by default.
- Staff can choose all days in a range, selected weekdays, or a generated occurrence list with per-date time overrides.
- The system validates the full generated set before writing any records. If any occurrence conflicts with a room booking or class session, no partial booking set is created.
- Staff can clone an existing booking into a new draft with the same room, title, type, priority, contact fields, and time values, while choosing new dates before submit.
- Staff can view an availability board that shows rooms, buildings, free windows, booked windows, class sessions, and room booking conflicts for a selected date range/time window.

## Affected Users

- Staff and admins who create room bookings.
- Room managers who approve or inspect booking requests.
- Operations staff who need to find free rooms across multiple days.

## Portal Impact

none

## Affected Product Docs

- `docs/db-flow.md`
- `docs/design-guidelines.md`
- A new or updated room booking product note should be added if implementation changes the persisted series semantics.

## Non-Goals

- No student or lecturer portal changes.
- No automatic recurring booking engine beyond the generated occurrence set.
- No cross-room booking set in the first slice; one booking plan targets one selected room.
- No waitlist or automatic alternative-room assignment in the first slice.
- No editing a whole series after creation unless explicitly approved as a follow-up story.

## Open Product Decisions

- Resolved: conflicts block the entire generated set; no partial booking set is created.
- Resolved: each generated occurrence keeps the current single-row status and approval workflow.
- Resolved: editable generated occurrence grid is sufficient for the first slice; no odd/even date bulk-fill control is required.
- Resolved: migrate room booking web routes/controllers to a new module structure and add the availability board as a dedicated module route/page.
