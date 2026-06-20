# Design - S-003

## Domain Model

Primary concept: a scheduled exam-resit block (`exam_room_slot`) is room occupancy that must block an overlapping room booking, symmetric to how `ExamScheduleConflictChecker` already blocks an exam block from overlapping a booking.

Invariants:

- Only `exam_room_slots` with `status = ExamRoomSlot::STATUS_SCHEDULED` block a booking.
- Overlap is half-open: `slot.start_time < booking.end AND slot.end_time > booking.start` on the same `exam_date`/`room_id`. Boundary-touching does not conflict.
- An exam slot whose `room_booking_id` equals the booking being excluded (edit/approve of that same booking) is skipped, so a booking is never blocked by its own mirror.
- Enforcement is read-only against exam data; no exam rows are written.

## Application Flow

### New shared predicate

`App\Modules\Facilities\Support\ExamSlotBookingConflictChecker`

```php
public function conflictsFor(
    int $roomId,
    string $date,
    string $startTime,
    string $endTime,
    ?int $excludeBookingId = null,
): array
```

- Queries `ExamRoomSlot` for `room_id = $roomId`, `whereDate('exam_date', $date)`, `status = scheduled`, half-open `TIME(start_time) < endTime AND TIME(end_time) > startTime`.
- Skips slots whose `room_booking_id = $excludeBookingId` when provided.
- Eager-loads `sessions.unit` to build the label.
- Returns conflict rows in the shape used by existing conflict consumers:

```text
type             = 'exam_room_slot'
id               number
exam_room_slot_id number
title            string   // 'Thi lại: <unit codes>' or 'Thi lại'
booking_date     'Y-m-d'
start_time       'H:i'
end_time         'H:i'
status           'scheduled'
is_editable      false
```

### Wiring (no new endpoints)

| Surface | Method | Change |
|---------|--------|--------|
| Create series (hard block) | `RoomBookingSlotValidator::conflictsFor()` | Append exam conflicts (covers `PreviewRoomBookingSeriesAvailabilityQuery` → `CreateRoomBookingSeriesAction` and `apiPreviewSeries`). |
| Single-day preview API | `RoomBookingService::checkAllConflicts()` | Append exam conflicts to the returned array. |
| Update (hard block) | `RoomBookingService::checkForConflicts()` | Throw `InvalidArgumentException` when an exam overlap exists. |
| Approve (hard block) | `RoomBookingService::approveBooking()` | Before flipping to approved, throw when the booking now overlaps a scheduled exam block (exclude the booking's own mirror). |

- The checker is constructor-injected into both `RoomBookingSlotValidator` (module) and `RoomBookingService` (legacy). Both already cross the module/legacy boundary (the module validator already depends on `App\Services\SystemConfigService`), so this adds no new boundary violation.
- `RoomBookingSlotValidator::conflictsFor()` already returns booking + class-session rows; exam rows are appended to the same array, so `PreviewRoomBookingSeriesAvailabilityQuery` marks the occurrence unavailable and `CreateRoomBookingSeriesAction` throws `RoomBookingSeriesConflictException` with no further change.

### Approve-time message

- The approve guard throws `InvalidArgumentException` with a clear message (e.g. "Phòng đã có ca thi lại trùng khung giờ; không thể duyệt đặt phòng này."). The controller `approve()` currently does not catch `InvalidArgumentException`; add a catch that flashes the error and returns back (mirrors `store()`/`update()` error handling) so approval failure is surfaced, not fatal.

## Data Model

- No migration. Read-only use of `exam_room_slots`, `exam_resit_sessions`, `units`.
- Reuses `exam_room_slot_conflict_idx` (room_id, exam_date, start_time, end_time) for the overlap query.

## UI / Platform Impact

- No new UI. The create form already renders conflict rows from the preview payload; exam rows now appear there with the existing conflict treatment.
- Approve failure surfaces via the standard flash/error path.

## Observability

- Log nothing new on the read predicate. The approve guard may log at info when it blocks an approval (optional, low value); keep parity with existing approve which does not log denials.

## Alternatives Considered

1. Reuse Academic's `ExamScheduleConflictChecker` directly from the booking side. Rejected: it is Academic-module-owned, throws `ValidationException` keyed to exam fields, and models the exam-creation direction. A small Facilities-owned predicate keeps module ownership clean (the same way class-session overlap logic already exists on both sides).
2. Only block at create, not approve. Rejected: an exam can be scheduled while a booking sits pending, so approve is the last safe gate.
3. Centralize the exam label builder shared with the S-002 board query. Deferred: the board already shipped with its own private `examTitle()`; duplicating a ~5-line label avoids re-touching shipped code (KISS). Revisit if a third consumer appears.
4. Soft warn instead of hard block. Rejected by product decision (hard block).
