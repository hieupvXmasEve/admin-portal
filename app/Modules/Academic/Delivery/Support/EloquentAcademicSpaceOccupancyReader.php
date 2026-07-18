<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\ClassSession;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Shared\Contracts\Academic\AcademicSpaceOccupancyReader;
use App\Shared\Contracts\Academic\DTO\AcademicSpaceOccupancy;

class EloquentAcademicSpaceOccupancyReader implements AcademicSpaceOccupancyReader
{
    /**
     * @param  list<int>  $roomIds
     * @return list<AcademicSpaceOccupancy>
     */
    public function forRooms(array $roomIds, string $startDate, string $endDate): array
    {
        if ($roomIds === []) {
            return [];
        }

        $classSessions = ClassSession::query()
            ->with(['courseOffering.unit', 'lecture'])
            ->whereIn('room_id', $roomIds)
            ->whereBetween('session_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'postponed', 'moved'])
            ->get()
            ->map(fn (ClassSession $session) => new AcademicSpaceOccupancy(
                type: 'class_session',
                sourceId: $session->id,
                roomId: $session->room_id,
                date: $session->session_date->format('Y-m-d'),
                startTime: $this->formatTime($session->start_time),
                endTime: $this->formatTime($session->end_time),
                title: ($session->courseOffering?->unit?->code ?? 'Class').' - '.($session->courseOffering?->unit?->name ?? $session->session_title),
                status: $session->status,
            ));

        $examSlots = ExamRoomSlot::query()
            ->with(['sessions:id,exam_room_slot_id,unit_id', 'sessions.unit:id,code,name'])
            ->whereIn('room_id', $roomIds)
            ->whereBetween('exam_date', [$startDate, $endDate])
            ->where('status', ExamRoomSlot::STATUS_SCHEDULED)
            ->get()
            ->map(fn (ExamRoomSlot $slot) => new AcademicSpaceOccupancy(
                type: 'exam_room_slot',
                sourceId: $slot->id,
                roomId: $slot->room_id,
                date: $slot->exam_date->format('Y-m-d'),
                startTime: $this->formatTime($slot->start_time),
                endTime: $this->formatTime($slot->end_time),
                title: $this->examTitle($slot),
                status: $slot->status,
                reservationId: $slot->room_booking_id,
            ));

        return $classSessions
            ->concat($examSlots)
            ->values()
            ->all();
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
