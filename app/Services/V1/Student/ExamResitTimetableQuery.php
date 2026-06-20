<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\ExamResitAttempt;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Targeted exam-resit (thi lại) timetable source for a student (ACAD-RET-001 slice 9).
 *
 * Returns ONLY the sittings this student is assigned to (their own scheduled/sat
 * attempts), so a resit in a shared room slot never leaks to other students. The
 * items merge into the student weekly timetable alongside normal class sessions,
 * labelled `item_type = exam_resit`.
 */
class ExamResitTimetableQuery
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(Student $student, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $start = $rangeStart->copy()->startOfDay();
        $end = $rangeEnd->copy()->endOfDay();

        return ExamResitAttempt::query()
            ->where('student_id', $student->id)
            ->whereNotNull('exam_resit_session_id')
            ->whereIn('status', [
                ExamResitAttempt::STATUS_SCHEDULED,
                ExamResitAttempt::STATUS_COMPLETED,
                ExamResitAttempt::STATUS_NO_SHOW,
            ])
            ->with([
                'unit:id,code,name',
                'session:id,exam_room_slot_id,unit_id,instructions',
                'session.roomSlot:id,room_id,exam_date,start_time,end_time',
                'session.roomSlot.room:id,code,name',
                'session.roomSlot.invigilators:id,exam_room_slot_id,lecture_id,role',
                'session.roomSlot.invigilators.lecture:id,first_name,last_name',
            ])
            ->get()
            ->filter(function (ExamResitAttempt $attempt) use ($start, $end): bool {
                $slot = $attempt->session?->roomSlot;

                return $slot !== null && $slot->exam_date !== null
                    && $slot->exam_date->betweenIncluded($start, $end);
            })
            ->map(fn (ExamResitAttempt $attempt): array => $this->format($attempt))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function format(ExamResitAttempt $attempt): array
    {
        $session = $attempt->session;
        $slot = $session->roomSlot;

        return [
            'id' => $attempt->id,
            'item_type' => 'exam_resit',
            'exam_date' => $slot->exam_date->format('Y-m-d'),
            'day_of_week' => strtolower($slot->exam_date->format('l')),
            'start_time' => $slot->start_time?->format('H:i'),
            'end_time' => $slot->end_time?->format('H:i'),
            'status' => $attempt->status,
            'payment_status' => $attempt->hq_fee_status,
            'unit' => [
                'code' => $attempt->unit?->code,
                'name' => $attempt->unit?->name,
            ],
            'room' => [
                'code' => $slot->room?->code,
                'name' => $slot->room?->name,
            ],
            'instructions' => $session->instructions,
            'invigilators' => $slot->invigilators->map(fn ($invigilator): array => [
                'name' => $invigilator->lecture?->full_name,
                'role' => $invigilator->role,
            ])->values()->toArray(),
            'color' => '#7C3AED',
        ];
    }
}
