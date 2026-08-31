<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Modules\Academic\Delivery\Support\ExamScheduleConflictChecker;
use App\Modules\Academic\Delivery\Support\ResitFeeGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Assign an approved exam-resit (thi lại) attempt to a unit-scoped session,
 * moving it to the `scheduled` state (ACAD-RET-001 slice 5).
 *
 * Guards:
 * - only an `approved` attempt can be scheduled;
 * - the session must be live and for the SAME unit/campus as the attempt;
 * - the session's planned seats (expected_candidates) cap how many attempts it
 *   can hold;
 * - the student must be free of overlapping enrolled class sessions and other
 *   scheduled exam-resit sessions;
 * - scheduling before payment is allowed only when the snapshotted policy permits
 *   an unpaid sitting AND a visible reason is recorded (payment stays a parallel
 *   HQ state, so an unpaid-but-scheduled row remains visible to HQ).
 */
class ScheduleExamResitAttemptAction
{
    public function __construct(
        private readonly ExamScheduleConflictChecker $conflicts,
        private readonly ResitFeeGate $resitFeeGate,
    ) {}

    /**
     * @param  array{
     *   attempt_id: int,
     *   exam_resit_session_id: int,
     *   unpaid_sitting_reason?: string|null,
     *   notes?: string|null,
     * }  $data
     */
    public function run(array $data): ExamResitAttempt
    {
        return DB::transaction(function () use ($data): ExamResitAttempt {
            $attempt = ExamResitAttempt::query()
                ->lockForUpdate()
                ->findOrFail($data['attempt_id']);

            $this->assertCanSchedule($attempt);

            $session = ExamResitSession::query()
                ->with('roomSlot')
                ->lockForUpdate()
                ->findOrFail($data['exam_resit_session_id']);

            $this->assertSessionMatchesAttempt($session, $attempt);

            $slot = $session->roomSlot;
            $examDate = $slot->exam_date->format('Y-m-d');
            $startTime = $slot->start_time->format('H:i:s');
            $endTime = $slot->end_time->format('H:i:s');

            $this->assertSessionHasRoom($session);
            $this->conflicts->assertStudentAvailable(
                (int) $attempt->student_id,
                $examDate,
                $startTime,
                $endTime,
                (int) $attempt->id,
            );

            $unpaidAttributes = $this->resitFeeGate->assertCanProceed(
                $attempt,
                $data,
                'Lệ phí thi lại chưa thanh toán, không thể xếp lịch khi chính sách không cho phép.',
                'Cần ghi rõ lý do xếp lịch thi lại khi chưa thanh toán.',
            );

            $attempt->update($unpaidAttributes + [
                'status' => ExamResitAttempt::STATUS_SCHEDULED,
                'exam_resit_session_id' => $session->id,
                'scheduled_at' => now(),
                'scheduled_by_user_id' => auth()->id(),
                'notes' => $this->mergeNotes($attempt->notes, $data['notes'] ?? null),
            ]);

            $this->refreshSessionCandidateCount($session);

            Log::info('Exam resit attempt scheduled', [
                'exam_resit_attempt_id' => $attempt->id,
                'exam_resit_session_id' => $session->id,
                'exam_room_slot_id' => $slot->id,
                'student_id' => $attempt->student_id,
                'unit_id' => $attempt->unit_id,
                'unpaid_before_payment' => $unpaidAttributes !== [],
                'scheduled_by_user_id' => auth()->id(),
            ]);

            return $attempt->fresh();
        });
    }

    private function assertCanSchedule(ExamResitAttempt $attempt): void
    {
        if ($attempt->status !== ExamResitAttempt::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ lần thi lại đã được duyệt mới có thể xếp lịch.'],
            ]);
        }
    }

    private function assertSessionMatchesAttempt(ExamResitSession $session, ExamResitAttempt $attempt): void
    {
        if ($session->status !== ExamResitSession::STATUS_SCHEDULED) {
            throw ValidationException::withMessages([
                'exam_resit_session_id' => ['Ca thi lại không còn ở trạng thái có thể xếp lịch.'],
            ]);
        }

        if ((int) $session->unit_id !== (int) $attempt->unit_id) {
            throw ValidationException::withMessages([
                'exam_resit_session_id' => ['Ca thi lại không cùng môn với lần thi lại của sinh viên.'],
            ]);
        }

        if ((int) $session->campus_id !== (int) $attempt->campus_id) {
            throw ValidationException::withMessages([
                'exam_resit_session_id' => ['Ca thi lại không cùng cơ sở với lần thi lại của sinh viên.'],
            ]);
        }

        $alreadyScheduled = ExamResitAttempt::query()
            ->where('exam_resit_session_id', $session->id)
            ->where('status', ExamResitAttempt::STATUS_SCHEDULED)
            ->count();

        if ($alreadyScheduled >= (int) $session->expected_candidates) {
            throw ValidationException::withMessages([
                'exam_resit_session_id' => ['Ca thi lại đã đủ số thí sinh dự kiến.'],
            ]);
        }
    }

    private function assertSessionHasRoom(ExamResitSession $session): void
    {
        if ($session->roomSlot === null) {
            throw ValidationException::withMessages([
                'exam_resit_session_id' => ['Ca thi lại chưa gắn với phòng/khung giờ thi.'],
            ]);
        }
    }

    private function refreshSessionCandidateCount(ExamResitSession $session): void
    {
        $count = ExamResitAttempt::query()
            ->where('exam_resit_session_id', $session->id)
            ->where('status', ExamResitAttempt::STATUS_SCHEDULED)
            ->count();

        $session->update(['actual_candidates' => $count]);
    }

    private function mergeNotes(?string $existing, ?string $incoming): ?string
    {
        $incoming = $incoming !== null ? trim($incoming) : '';

        if ($incoming === '') {
            return $existing;
        }

        return $existing ? $existing."\n".$incoming : $incoming;
    }
}
