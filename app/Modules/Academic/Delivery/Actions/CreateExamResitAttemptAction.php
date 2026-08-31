<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\AcademicRecord;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\SyllabusTemplate;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

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
            $this->assertAttemptsRemaining($record, (int) $policySnapshot['max_attempts']);

            $requestSequence = ExamResitAttempt::query()
                ->where('academic_record_id', $record->id)
                ->count() + 1;

            $userId = (int) auth()->id();

            $attempt = ExamResitAttempt::create([
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
                'requested_by_user_id' => $userId,
                'requested_at' => now(),
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'status' => ExamResitAttempt::STATUS_APPROVED,
                'request_sequence' => $requestSequence,
                'attempt_number' => null,
                'approved_at' => now(),
                'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
                'policy_snapshot' => $policySnapshot,
                'max_attempts_snapshot' => $policySnapshot['max_attempts'],
                'late_payment_grace_days_snapshot' => $policySnapshot['late_payment_grace_days'],
                'allow_unpaid_sitting_snapshot' => $policySnapshot['allow_unpaid_sitting'],
                'notes' => $data['notes'] ?? null,
            ]);

            $unit = $record->unit;

            try {
                $intakeResult = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
                    source_system: AcademicFinanceObligationSource::SOURCE_SYSTEM,
                    source_kind: AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
                    source_ref: AcademicFinanceObligationSource::examResitAttemptRef($attempt),
                    financial_effect: FinancialEffect::Debit,
                    obligation_type: AcademicFinanceObligationSource::EXAM_RESIT_FEE,
                    facts: [
                        'student_id' => $attempt->student_id,
                        'semester_id' => $attempt->charge_semester_id,
                        'campus_id' => $attempt->campus_id,
                        'unit_id' => $attempt->unit_id,
                        'academic_record_id' => $attempt->academic_record_id,
                        'original_course_offering_id' => $attempt->original_course_offering_id,
                        'original_semester_id' => $attempt->original_semester_id,
                        'operation_semester_id' => $attempt->operation_semester_id,
                        'charge_semester_id' => $attempt->charge_semester_id,
                        'request_sequence' => $attempt->request_sequence,
                        'syllabus_template_id' => $attempt->syllabus_template_id,
                        'max_attempts_snapshot' => $attempt->max_attempts_snapshot,
                        'late_payment_grace_days_snapshot' => $attempt->late_payment_grace_days_snapshot,
                        'allow_unpaid_sitting_snapshot' => $attempt->allow_unpaid_sitting_snapshot,
                        'description' => "Phí thi lại: {$unit?->code} - {$unit?->name}",
                    ],
                ));
            } catch (RuntimeException $e) {
                if (! str_contains($e->getMessage(), 'No active Finance pricing catalog item')) {
                    throw $e;
                }

                throw ValidationException::withMessages([
                    'policy' => ['Chưa cấu hình giá thi lại cho môn này. Vui lòng cấu hình tại Pricing Operations.'],
                ]);
            }

            $attempt->markFinanceObligationCreated($userId, $intakeResult->amount);

            return $attempt->fresh();
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

        $hasInFlightAttempt = ExamResitAttempt::query()
            ->where('academic_record_id', $record->id)
            ->whereIn('status', ExamResitAttempt::IN_FLIGHT_STATUSES)
            ->exists();

        if ($hasInFlightAttempt) {
            throw ValidationException::withMessages([
                'academic_record_id' => ['Bản ghi này đã có đăng ký thi lại đang xử lý.'],
            ]);
        }

        // Cross-lane guard: a record already being handled in the retake lane must
        // not also be registered for exam-resit.
        $hasActiveRetake = CourseRetakeRegistration::query()
            ->where('original_academic_record_id', $record->id)
            ->nonTerminal()
            ->exists();

        if ($hasActiveRetake) {
            throw ValidationException::withMessages([
                'academic_record_id' => ['Bản ghi này đã có đăng ký học lại đang xử lý.'],
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
     * @return array{max_attempts:int,registration_window_days:?int,late_payment_grace_days:int,allow_unpaid_sitting:bool}
     */
    private function buildPolicySnapshot(SyllabusTemplate $syllabus): array
    {
        return [
            'max_attempts' => max(1, (int) ($syllabus->exam_resit_max_attempts ?? 1)),
            'registration_window_days' => $syllabus->exam_resit_registration_window_days,
            'late_payment_grace_days' => (int) ($syllabus->exam_resit_late_payment_grace_days ?? 14),
            'allow_unpaid_sitting' => (bool) ($syllabus->exam_resit_allow_unpaid_sitting ?? false),
        ];
    }

    private function assertAttemptsRemaining(AcademicRecord $record, int $maxAttempts): void
    {
        $consumedAttempts = ExamResitAttempt::consumedAttemptCount($record->id);

        if ($consumedAttempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'academic_record_id' => ['Sinh viên đã dùng hết số lần thi lại cho môn này.'],
            ]);
        }
    }

    /**
     * Live attempt policy for a record: the current syllabus max, not a
     * per-row snapshot. Raising the syllabus max re-opens the lane without
     * code changes (owner rule #2). Lenient for display contexts: a record
     * without any syllabus policy resolves to the default of 1 instead of
     * throwing (the write guard still fails closed).
     */
    public static function liveMaxAttemptsFor(AcademicRecord $record): int
    {
        $syllabus = $record->courseOffering?->syllabusTemplate
            ?? SyllabusTemplate::query()
                ->where('unit_id', $record->unit_id)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->latest('id')
                ->first();

        return max(1, (int) ($syllabus->exam_resit_max_attempts ?? 1));
    }
}
