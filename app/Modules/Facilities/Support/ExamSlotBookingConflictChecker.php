<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Modules\Academic\Services\ExamScheduleConflictChecker;

/**
 * Detects whether a proposed room booking window overlaps a scheduled
 * exam-resit block (ACAD-RET-001 slice 5 scheduling) in the same room.
 *
 * This is the booking-side counterpart to Academic's
 * {@see ExamScheduleConflictChecker}, which guards
 * the exam-creation direction. Both use the half-open overlap rule
 * `existing.start < new.end AND existing.end > new.start`, so blocks that merely
 * touch at a boundary (e.g. 09:00-11:00 then 11:00-13:00) do NOT conflict. Only
 * `scheduled` slots occupy a room; completed/cancelled slots are ignored.
 */
class ExamSlotBookingConflictChecker
{
    /**
     * Return exam-overlap conflict rows for a room/date/time window, shaped like
     * the other room-booking conflict sources (room_booking, class_session).
     *
     * @return array<int, array<string, mixed>>
     */
    public function conflictsFor(
        int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null,
    ): array {
        return ExamRoomSlot::query()
            ->with(['sessions:id,exam_room_slot_id,unit_id', 'sessions.unit:id,code,name'])
            ->where('room_id', $roomId)
            ->whereDate('exam_date', $date)
            ->where('status', ExamRoomSlot::STATUS_SCHEDULED)
            // A booking is never blocked by an exam slot that merely mirrors that
            // same booking (exam_room_slots.room_booking_id), e.g. on edit/approve.
            ->when($excludeBookingId !== null, fn ($query) => $query->where(function ($inner) use ($excludeBookingId) {
                $inner->whereNull('room_booking_id')
                    ->orWhere('room_booking_id', '!=', $excludeBookingId);
            }))
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereRaw('TIME(start_time) < ?', [$endTime])
                    ->whereRaw('TIME(end_time) > ?', [$startTime]);
            })
            ->get()
            ->map(fn (ExamRoomSlot $slot) => [
                'type' => 'exam_room_slot',
                'id' => $slot->id,
                'exam_room_slot_id' => $slot->id,
                'title' => $this->examTitle($slot),
                'booking_date' => $slot->exam_date->format('Y-m-d'),
                'start_time' => $this->formatTime($slot->start_time),
                'end_time' => $this->formatTime($slot->end_time),
                'status' => $slot->status,
                'is_editable' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * True when the window overlaps at least one scheduled exam block.
     */
    public function hasConflict(
        int $roomId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null,
    ): bool {
        return $this->conflictsFor($roomId, $date, $startTime, $endTime, $excludeBookingId) !== [];
    }

    private function examTitle(ExamRoomSlot $slot): string
    {
        $unitCodes = $slot->sessions
            ->map(fn (ExamResitSession $session) => $session->unit?->code)
            ->filter()
            ->unique()
            ->values();

        return $unitCodes->isNotEmpty()
            ? 'Thi lại: '.$unitCodes->implode(', ')
            : 'Thi lại';
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
