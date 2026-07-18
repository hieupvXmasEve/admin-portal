<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use App\Shared\Contracts\Academic\AcademicSpaceOccupancyReader;

/**
 * Detects whether a proposed room booking window overlaps a scheduled exam
 * block in the same room. Delivery supplies this occupancy through its shared
 * reader; Facilities does not inspect Delivery persistence.
 *
 * The half-open overlap rule
 * `existing.start < new.end AND existing.end > new.start`, so blocks that merely
 * touch at a boundary (e.g. 09:00-11:00 then 11:00-13:00) do NOT conflict. Only
 * `scheduled` slots occupy a room; completed/cancelled slots are ignored.
 */
class ExamSlotBookingConflictChecker
{
    public function __construct(private readonly AcademicSpaceOccupancyReader $academicSpaceOccupancyReader) {}

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
        return collect($this->academicSpaceOccupancyReader->forRooms([$roomId], $date, $date))
            ->filter(fn ($occupancy) => $occupancy->type === 'exam_room_slot')
            ->filter(fn ($occupancy) => $occupancy->endTime > $startTime && $occupancy->startTime < $endTime)
            ->reject(fn ($occupancy) => $occupancy->reservationId !== null && $occupancy->reservationId === $excludeBookingId)
            ->map(fn ($occupancy) => [
                'type' => $occupancy->type,
                'id' => $occupancy->sourceId,
                'exam_room_slot_id' => $occupancy->sourceId,
                'title' => $occupancy->title,
                'booking_date' => $occupancy->date,
                'start_time' => $occupancy->startTime,
                'end_time' => $occupancy->endTime,
                'status' => $occupancy->status,
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
}
