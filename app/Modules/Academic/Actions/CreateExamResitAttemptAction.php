<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\AcademicRecord;
use App\Models\ExamResitAttempt;
use App\Models\SyllabusTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateExamResitAttemptAction
{
    /**
     * @param  array{
     *   student_id: int,
     *   academic_record_id: int,
     *   operation_semester_id: int,
     *   charge_semester_id: int,
     *   campus_id: int,
     *   notes?: string|null,
     * }  $data
     */
    public function run(array $data): ExamResitAttempt
    {
        return DB::transaction(function () use ($data): ExamResitAttempt {
            $record = AcademicRecord::query()
                ->with(['courseOffering.syllabusTemplate', 'unit'])
                ->lockForUpdate()
                ->findOrFail($data['academic_record_id']);

            $this->assertRecordCanEnterExamResit($record, $data);

            $syllabus = $this->resolveSyllabus($record);
            $policySnapshot = $this->buildPolicySnapshot($syllabus);
            $this->assertPolicyAllowsExamResit($policySnapshot);
            $this->assertAttemptsRemaining($record, (int) $policySnapshot['max_attempts']);

            $requestSequence = ExamResitAttempt::query()
                ->where('academic_record_id', $record->id)
                ->count() + 1;

            return ExamResitAttempt::create([
                'student_id' => $record->student_id,
                'academic_record_id' => $record->id,
                'original_course_offering_id' => $record->course_offering_id,
                'unit_id' => $record->unit_id,
                'campus_id' => $record->campus_id,
                'syllabus_template_id' => $syllabus->id,
                'original_semester_id' => $record->semester_id,
                'operation_semester_id' => $data['operation_semester_id'],
                'charge_semester_id' => $data['charge_semester_id'],
                'request_origin' => ExamResitAttempt::REQUEST_ORIGIN_STAFF,
                'requested_by_user_id' => auth()->id(),
                'requested_at' => now(),
                'reviewed_by_user_id' => auth()->id(),
                'reviewed_at' => now(),
                'status' => ExamResitAttempt::STATUS_APPROVED,
                'request_sequence' => $requestSequence,
                'attempt_number' => null,
                'approved_at' => now(),
                'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
                'fee_amount' => $policySnapshot['exam_resit_fee'],
                'policy_snapshot' => $policySnapshot,
                'max_attempts_snapshot' => $policySnapshot['max_attempts'],
                'exam_resit_fee_snapshot' => $policySnapshot['exam_resit_fee'],
                'late_payment_grace_days_snapshot' => $policySnapshot['late_payment_grace_days'],
                'allow_unpaid_sitting_snapshot' => $policySnapshot['allow_unpaid_sitting'],
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertRecordCanEnterExamResit(AcademicRecord $record, array $data): void
    {
        if ((int) $record->student_id !== (int) $data['student_id']) {
            throw ValidationException::withMessages([
                'student_id' => ['Bản ghi học tập không thuộc sinh viên đã chọn.'],
            ]);
        }

        if ((int) $record->campus_id !== (int) $data['campus_id']) {
            throw ValidationException::withMessages([
                'campus_id' => ['Bản ghi học tập không thuộc cơ sở đã chọn.'],
            ]);
        }

        if ((bool) $record->is_passed || (bool) $record->override_pass) {
            throw ValidationException::withMessages([
                'academic_record_id' => ['Sinh viên đã pass môn này, không đủ điều kiện thi lại.'],
            ]);
        }

        if ($record->completion_status === 'in_progress' || $record->grade_status !== 'final') {
            throw ValidationException::withMessages([
                'academic_record_id' => ['Bản ghi học tập chưa final, chưa thể xét thi lại.'],
            ]);
        }

        if ($record->failure_reason === null) {
            throw ValidationException::withMessages([
                'failure_reason' => ['Thi lại cần failure_reason rõ ràng từ Academic finalization.'],
            ]);
        }

        if (in_array($record->failure_reason, [
            AcademicRecord::FAILURE_ATTENDANCE_FAILED,
            AcademicRecord::FAILURE_BOTH_FAILED,
        ], true)) {
            throw ValidationException::withMessages([
                'failure_reason' => ['Sinh viên fail do chuyên cần phải đi luồng học lại, không đủ điều kiện thi lại.'],
            ]);
        }

        $snapshot = $record->failure_reason_snapshot ?? [];
        if (($snapshot['attendance_evidence_state'] ?? null) === 'not_recorded' || (int) $record->total_not_recorded > 0) {
            throw ValidationException::withMessages([
                'failure_reason' => ['Dữ liệu chuyên cần chưa ghi nhận đủ, chưa thể chốt điều kiện thi lại.'],
            ]);
        }
    }

    private function resolveSyllabus(AcademicRecord $record): SyllabusTemplate
    {
        $syllabus = $record->courseOffering?->syllabusTemplate;

        if (! $syllabus) {
            $syllabus = SyllabusTemplate::query()
                ->where('unit_id', $record->unit_id)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->latest('id')
                ->first();
        }

        if (! $syllabus) {
            throw ValidationException::withMessages([
                'academic_record_id' => ['Không tìm thấy syllabus policy cho môn thi lại.'],
            ]);
        }

        return $syllabus;
    }

    /**
     * @return array{max_attempts:int,exam_resit_fee:float,registration_window_days:?int,late_payment_grace_days:int,allow_unpaid_sitting:bool}
     */
    private function buildPolicySnapshot(SyllabusTemplate $syllabus): array
    {
        return [
            'max_attempts' => max(1, (int) ($syllabus->exam_resit_max_attempts ?? 1)),
            'exam_resit_fee' => (float) ($syllabus->exam_resit_fee ?? 0),
            'registration_window_days' => $syllabus->exam_resit_registration_window_days,
            'late_payment_grace_days' => (int) ($syllabus->exam_resit_late_payment_grace_days ?? 14),
            'allow_unpaid_sitting' => (bool) ($syllabus->exam_resit_allow_unpaid_sitting ?? false),
        ];
    }

    /**
     * @param  array{exam_resit_fee:float}  $policySnapshot
     */
    private function assertPolicyAllowsExamResit(array $policySnapshot): void
    {
        if ($policySnapshot['exam_resit_fee'] <= 0) {
            throw ValidationException::withMessages([
                'policy' => ['Syllabus chưa cấu hình exam_resit_fee hợp lệ.'],
            ]);
        }
    }

    private function assertAttemptsRemaining(AcademicRecord $record, int $maxAttempts): void
    {
        $consumedAttempts = ExamResitAttempt::query()
            ->where('academic_record_id', $record->id)
            ->whereNotNull('attempt_number')
            ->count();

        if ($consumedAttempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'academic_record_id' => ['Sinh viên đã dùng hết số lần thi lại cho môn này.'],
            ]);
        }
    }
}
