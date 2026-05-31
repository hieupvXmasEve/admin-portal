# Design - S-001

## Domain Model

Primary domain concept: a room booking plan expands into dated room booking occurrences.

Invariants:

- One occurrence maps to one `room_bookings` row with one `booking_date`, `start_time`, and `end_time`.
- Active conflicts are detected against `room_bookings` with `pending` or `approved` status and against `class_sessions` that are not cancelled or postponed.
- A generated set is transactional: either every occurrence is valid and created, or none are created.
- Generated children should be grouped with existing `parent_booking_id` and recurrence metadata where possible instead of adding a new table in the first slice.
- Clone produces a draft only; it must not duplicate status, approval fields, rejection reason, cancellation fields, or action history.

## Application Flow

### Commands

- `CreateRoomBookingSeriesAction`: expands submitted date/time rules into occurrences, validates room bookability, validates each time slot, validates conflicts, creates records, and logs one `created` action per record inside one transaction.
- `CloneRoomBookingDraftAction` or query-equivalent draft builder: reads an existing booking and returns safe defaults for the create form.

### Queries

- `PreviewRoomBookingSeriesAvailabilityQuery`: accepts room, date range, selected dates/weekdays, and occurrence times; returns available/conflicting rows before submit.
- `GetRoomAvailabilityBoardQuery`: returns room rows and time-window occupancy for a building/date range/time window, combining active room bookings and class sessions.

## Interface Contract

Existing page:

- `/room-bookings/create` remains the main entry point.
- Optional `source_booking_id` query param can prefill a clone draft if the user can view the source booking and create room bookings.

Potential new web surface:

- Add a dedicated module route/page at `/room-bookings/availability`.
- The static availability route must be registered before `room-bookings/{roomBooking}` so Laravel never treats `availability` as a `{roomBooking}` parameter.
- Room booking web routes/controllers should move from the legacy top-level route/controller surface into a module-owned route/controller surface as part of this story.

Request shape for create:

```text
room_id
title
description
booking_type
priority
contact_person
contact_phone
contact_email
special_requirements
schedule_mode = single | range
booking_date
date_from
date_to
selected_weekdays[]
occurrences[] = [{ booking_date, start_time, end_time }]
```

Validation errors should point to:

- `occurrences` for aggregate conflicts.
- `occurrences.{index}.booking_date`, `start_time`, or `end_time` for per-row issues.
- `room_id` for unbookable room or campus mismatch.

## Data Model

Preferred first slice:

- No migration.
- Use existing `room_bookings.parent_booking_id`, `is_recurring`, `recurrence_type`, `recurrence_end_date`, and `recurrence_days` to group generated rows when the set has more than one occurrence.

Escalate to a migration only if discovery shows existing recurrence columns cannot safely represent the plan or if staff need a durable series-level approval/edit object.

## UI / Platform Impact

Create page should become a booking planner:

- Keep the single-day form as the default mode for current users.
- Add a segmented control: `Single day` and `Date range`.
- Date range mode shows start date, end date, weekday selectors, and an `All days` checkbox.
- Time controls use the existing `DatePicker` and `TimePicker` components, not native date/time inputs.
- Default behavior applies the same start/end time to all generated occurrences.
- If times differ, show an occurrence grid where rows are auto-filled and staff can edit start/end time per date.
- Provide bulk fill actions for common cases: all rows, selected weekdays, and odd/even dates if accepted for the first slice.
- Show availability preview inline before submit: available rows, conflicting rows, conflict source, title, status, and time range.
- Disable final submit while preview is stale or any conflict exists, unless the accepted behavior allows skipping conflicting rows.
- Final submit is disabled when any generated occurrence conflicts; the first slice does not create only the available rows.

Availability board:

- Add filters for building, room, date range, and daily time window.
- Show rooms as rows and dates/time blocks as columns or timeline chips.
- Distinguish room bookings from class sessions.
- Show free windows, not only occupied windows.
- Keep layout dense and operational, not marketing-style.

Clone workflow:

- Add clone action buttons on show/index where the user has create permission.
- Navigate to create with a clone source, prefill safe fields, and force date/date-range confirmation before submit.

## Observability

- Log series creation with user id, room id, occurrence count, date range, status outcome, and conflict count.
- Existing `room_booking_actions` records remain the audit trail for each created booking.
- If series-level grouping is used, include parent and child ids in the action metadata where possible.

## Alternatives Considered

1. Store one row with `booking_date` range. Rejected for first slice because conflict checks, calendar rendering, approval status, and existing list pages are date-row oriented.
2. Build a full recurring-series table immediately. Deferred unless product approval requires series-level approval/edit/history.
3. Let frontend create many single-day bookings one by one. Rejected because partial creation and race conditions would be likely.
4. Add availability as a tab on `/room-bookings` to avoid route migration. Rejected by product decision; this story will migrate the room booking route/controller surface into the module structure.
