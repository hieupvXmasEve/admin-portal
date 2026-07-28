<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\ExamRoomSlot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Exam room-slot management list (ACAD-RET-001 slice 7).
 *
 * One slot is a room + date/time block that may host several unit-scoped
 * exam-resit sessions and shared invigilators. Returns seat usage so staff can
 * see remaining capacity before adding a session or assigning attempts.
 */
class ListExamRoomSlotsQuery
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array{slots:LengthAwarePaginator}
     */
    public function handle(array $filters, ?int $campusId): array
    {
        $slots = ExamRoomSlot::query()
            ->with([
                'room:id,name,code',
                'sessions:id,exam_room_slot_id,unit_id,status,expected_candidates,actual_candidates',
                'sessions.unit:id,code,name',
                'invigilators:id,exam_room_slot_id,lecture_id,role',
                'invigilators.lecture:id,first_name,last_name',
            ])
            ->when($campusId, fn (Builder $q, int $id) => $q->where('campus_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['exam_date'] ?? null, fn (Builder $q, string $date) => $q->whereDate('exam_date', $date))
            ->orderByDesc('exam_date')
            ->orderBy('start_time')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString()
            ->through(fn (ExamRoomSlot $slot): array => $this->toRow($slot));

        return ['slots' => $slots];
    }

    /**
     * @return array<string,mixed>
     */
    private function toRow(ExamRoomSlot $slot): array
    {
        $sessions = $slot->sessions;

        return [
            'id' => $slot->id,
            'exam_date' => $slot->exam_date?->format('Y-m-d'),
            'start_time' => $slot->start_time?->format('H:i'),
            'end_time' => $slot->end_time?->format('H:i'),
            'status' => $slot->status,
            'room' => $slot->room,
            'seats_total' => (int) $slot->capacity,
            'seats_used' => (int) $sessions->sum('expected_candidates'),
            'seats_assigned' => (int) $sessions->sum('actual_candidates'),
            'sessions' => $sessions->map(fn ($session): array => [
                'id' => $session->id,
                'unit' => $session->unit,
                'status' => $session->status,
                'expected_candidates' => (int) $session->expected_candidates,
                'actual_candidates' => (int) $session->actual_candidates,
            ])->values(),
            'invigilators' => $slot->invigilators->map(fn ($invigilator): array => [
                'id' => $invigilator->id,
                'role' => $invigilator->role,
                'lecture' => $invigilator->lecture ? [
                    'id' => $invigilator->lecture->id,
                    'name' => $invigilator->lecture->full_name,
                ] : null,
            ])->values(),
        ];
    }
}
