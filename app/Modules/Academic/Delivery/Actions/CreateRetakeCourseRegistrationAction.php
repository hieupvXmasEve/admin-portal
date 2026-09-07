<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Support\NonCancelledRetakeRegistration;
use App\Modules\Academic\Delivery\Support\OccupiedExamResitAttempt;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Services\V1\Student\PrerequisiteValidationService;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateRetakeCourseRegistrationAction
{
    /**
     * Create a new Academic-owned retake course source.
     *
     * Flow: validate → create registration (approved/listed). HQ/Finance creates
     * the charge later from the source through the DNG/fee worklist.
     *
     * @param  array{
     *   student_id: int,
     *   unit_id: int,
     *   original_academic_record_id: int,
     *   course_offering_id?: int|null,
     *   semester_id: int,
     *   campus_id: int,
     *   registration_start_date?: string|null,
     *   registration_end_date?: string|null,
     *   notes?: string|null,
     * }  $data
     */
    public static function run(array $data): CourseRetakeRegistration
    {
        return DB::transaction(function () use ($data) {
            $student = app(StudentReferenceReader::class)->find((int) $data['student_id']);

            if ($student === null) {
                throw ValidationException::withMessages([
                    'student_id' => ['Không tìm thấy sinh viên.'],
                ]);
            }
            $unit = Unit::findOrFail($data['unit_id']);
            $courseOffering = isset($data['course_offering_id']) && $data['course_offering_id'] !== null
                ? CourseOffering::findOrFail($data['course_offering_id'])
                : null;

            // Validate student is intake_course
            if ($student->status !== 'intake_course') {
                throw ValidationException::withMessages([
                    'student_id' => ['Sinh viên phải ở trạng thái intake_course để đăng ký học lại.'],
                ]);
            }

            // Block retake if student already passed this unit in any record
            $alreadyPassed = AcademicRecord::where('student_id', $data['student_id'])
                ->where('unit_id', $data['unit_id'])
                ->where(fn ($q) => $q->where('is_passed', true)->orWhere('override_pass', true))
                ->exists();

            if ($alreadyPassed) {
                throw ValidationException::withMessages([
                    'unit_id' => ['Sinh viên đã pass môn này, không thể đăng ký học lại.'],
                ]);
            }

            $academicRecord = AcademicRecord::where('id', $data['original_academic_record_id'])
                ->where('student_id', $data['student_id'])
                ->where('unit_id', $data['unit_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ((bool) $academicRecord->is_passed || (bool) $academicRecord->override_pass) {
                throw ValidationException::withMessages([
                    'unit_id' => ['Sinh viên đã pass môn này, không thể đăng ký học lại.'],
                ]);
            }

            if ($academicRecord->completion_status === 'in_progress' || $academicRecord->grade_status !== 'final') {
                throw ValidationException::withMessages([
                    'original_academic_record_id' => ['Bản ghi học tập chưa chốt, chưa thể đăng ký học lại.'],
                ]);
            }

            $hasOccupiedResit = OccupiedExamResitAttempt::constrain(
                ExamResitAttempt::query()->where('academic_record_id', $academicRecord->id)
            )->exists();

            if ($hasOccupiedResit) {
                throw ValidationException::withMessages([
                    'failure_reason' => ['Bản ghi này đang có lượt thi lại chưa hoàn tất, không thể đăng ký học lại.'],
                ]);
            }

            $existingActive = NonCancelledRetakeRegistration::constrain(
                CourseRetakeRegistration::query()
                    ->where('original_academic_record_id', $academicRecord->id)
            )->exists();

            if ($existingActive) {
                throw ValidationException::withMessages([
                    'student_id' => ['Sinh viên đã có đăng ký học lại cho bản ghi này.'],
                ]);
            }

            // Class placement is optional at source creation time. When staff pick
            // a target class now, keep the existing prerequisite guard.
            if ($courseOffering) {
                $prereqMet = app(PrerequisiteValidationService::class)
                    ->hasMetPrerequisites($student->id, $courseOffering);
                if (! $prereqMet) {
                    $prereqDetails = app(PrerequisiteValidationService::class)
                        ->getPrerequisiteValidation($student->id, $courseOffering);
                    $missingCodes = collect($prereqDetails['missing_groups'])
                        ->flatMap(fn ($g) => collect($g['conditions'])
                            ->where('met', false)
                            ->pluck('unit.code')
                            ->filter()
                        )
                        ->unique()
                        ->implode(', ');
                    $msg = $missingCodes
                        ? "Sinh viên chưa hoàn thành điều kiện tiên quyết cho môn {$unit->code}: {$missingCodes}"
                        : "Sinh viên chưa đáp ứng điều kiện tiên quyết cho môn {$unit->code}";
                    throw ValidationException::withMessages(['unit_id' => [$msg]]);
                }
            }

            // Calculate attempt_number: count existing academic records + 1
            $previousAttempts = AcademicRecord::query()
                ->where('student_id', $data['student_id'])
                ->where('unit_id', $data['unit_id'])
                ->count();
            $attemptNumber = $previousAttempts + 1;

            $userId = (int) auth()->id();

            $registration = CourseRetakeRegistration::create([
                'student_id' => $data['student_id'],
                'unit_id' => $data['unit_id'],
                'original_academic_record_id' => $data['original_academic_record_id'],
                'course_offering_id' => $courseOffering?->id,
                'semester_id' => $data['semester_id'],
                'campus_id' => $data['campus_id'],
                'original_semester_id' => $academicRecord->semester_id,
                'operation_semester_id' => $data['semester_id'],
                'charge_semester_id' => $data['charge_semester_id'] ?? $data['semester_id'],
                'status' => CourseRetakeRegistration::STATUS_APPROVED,
                'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
                'requested_by_user_id' => $userId,
                'requested_at' => now(),
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_PENDING,
                'attempt_number' => $attemptNumber,
                'registration_start_date' => $data['registration_start_date'] ?? null,
                'registration_end_date' => $data['registration_end_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'approved_by_user_id' => $userId,
                'approved_at' => now(),
            ]);

            // The Finance pricing catalog is the single source of the retake fee.
            // Missing/inactive rule must stop creation with a staff-friendly
            // message (parity with the exam-resit lane) instead of a 500.
            try {
                $intakeResult = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
                    source_system: AcademicFinanceObligationSource::SOURCE_SYSTEM,
                    source_kind: AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                    source_ref: AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
                    financial_effect: FinancialEffect::Debit,
                    obligation_type: AcademicFinanceObligationSource::RETAKE_FEE,
                    facts: [
                        'student_id' => $registration->student_id,
                        'semester_id' => $registration->charge_semester_id ?? $registration->semester_id,
                        'campus_id' => $registration->campus_id,
                        'unit_id' => $registration->unit_id,
                        'course_offering_id' => $registration->course_offering_id,
                        'original_academic_record_id' => $registration->original_academic_record_id,
                        'original_semester_id' => $registration->original_semester_id,
                        'operation_semester_id' => $registration->operation_semester_id,
                        'charge_semester_id' => $registration->charge_semester_id,
                        'attempt_number' => $registration->attempt_number,
                        'student_program_id' => $student->programId,
                        'student_curriculum_version_id' => $student->curriculumVersionId,
                        'description' => "Phí học lại: {$unit->code} - {$unit->name}",
                    ],
                ));
            } catch (RuntimeException $e) {
                if (! str_contains($e->getMessage(), 'No active Finance pricing catalog item')) {
                    throw $e;
                }

                throw ValidationException::withMessages([
                    'unit_id' => ['Chưa cấu hình giá học lại cho môn này. Vui lòng cấu hình tại Pricing Operations.'],
                ]);
            }

            // Capture the amount Finance actually resolved and stored for this
            // source — never resolve it independently from unit.retake_fee.
            $registration->markFinanceObligationCreated($userId, $intakeResult->amount);

            return $registration->fresh();
        });
    }
}
