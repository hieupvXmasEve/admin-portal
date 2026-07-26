<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Shared\Contracts\Identity\LecturerTeachingActor as Lecture;
use Carbon\Carbon;

/**
 * Targeted exam-resit invigilation duty source for a lecturer (ACAD-RET-001 slice 9).
 *
 * Returns ONLY the room slots this lecturer is assigned to invigilate, so the duty
 * merges into the lecturer timetable alongside teaching sessions. Invigilation
 * attaches to the room slot (which may host several unit-scoped sessions), so each
 * duty lists the sessions inside the slot.
 */
class InvigilationDutyQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Lecture $lecturer, Carbon $startDate, Carbon $endDate): array
    {
        return ExamRoomSlotInvigilator::query()
            ->where('lecture_id', $lecturer->lecturerId())
            ->whereHas('roomSlot', fn ($query) => $query
                ->whereBetween('exam_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where('status', '!=', ExamRoomSlot::STATUS_CANCELLED))
            ->with([
                'roomSlot:id,room_id,exam_date,start_time,end_time,status',
                'roomSlot.room:id,code,name',
                'roomSlot.sessions:id,exam_room_slot_id,unit_id,status,expected_candidates,actual_candidates',
                'roomSlot.sessions.unit:id,code,name',
            ])
            ->get()
            ->filter(fn (ExamRoomSlotInvigilator $invigilator): bool => $invigilator->roomSlot !== null)
            ->map(fn (ExamRoomSlotInvigilator $invigilator): array => $this->format($invigilator))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function format(ExamRoomSlotInvigilator $invigilator): array
    {
        $slot = $invigilator->roomSlot;
        $sessions = $slot->sessions;

        return [
            'id' => $invigilator->id,
            'item_type' => 'invigilation',
            'exam_room_slot_id' => $slot->id,
            'role' => $invigilator->role,
            'exam_date' => $slot->exam_date->format('Y-m-d'),
            'day_of_week' => strtolower($slot->exam_date->format('l')),
            'start_time' => $slot->start_time?->format('H:i'),
            'end_time' => $slot->end_time?->format('H:i'),
            'room' => [
                'code' => $slot->room?->code,
                'name' => $slot->room?->name,
            ],
            'sessions' => $sessions->map(fn ($session): array => [
                'unit_code' => $session->unit?->code,
                'unit_name' => $session->unit?->name,
                'expected_candidates' => (int) $session->expected_candidates,
                'actual_candidates' => (int) $session->actual_candidates,
            ])->values()->toArray(),
            'session_count' => $sessions->count(),
            'notes' => $invigilator->notes,
        ];
    }
}
