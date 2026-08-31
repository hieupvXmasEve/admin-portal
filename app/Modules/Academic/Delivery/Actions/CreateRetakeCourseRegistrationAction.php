<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Unit;
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

            // Validate academic record exists and student did not pass
            // Uses is_passed field (not completion_status which indicates finalization state)
            $academicRecord = AcademicRecord::where('id', $data['original_academic_record_id'])
                ->where('student_id', $data['student_id'])
                ->where('unit_id', $data['unit_id'])
                ->where('is_passed', false)
                ->where('completion_status', '!=', 'in_progress')
                ->where(fn ($q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
                ->firstOrFail();

            // Failure-routing gate (ACAD-RET-001 Slice 2). Block the explicit
            // grade-only reason UNLESS the record already sat a completed exam-resit
            // attempt and is still failing — the resit path is exhausted, so it falls
            // back into the retake lane (mirrors ListRetakeCourseEligibleStudentsQuery).
            // Attendance/both/manual stay eligible, and legacy un-backfilled records
            // (failure_reason = null) keep their historical eligibility. NOTE: the
            // exam-resit lane (Delivery\Actions\CreateExamResitAttemptAction) no longer
            // excludes attendance/both/manual/null on failure_reason, so a non-grade-only
            // failure can now be registered in both lanes — the in-flight-resit guard
            // below only blocks while a sitting is pending, not after both are used.
            if ($academicRecord->failure_reason === AcademicRecord::FAILURE_GRADE_FAILED) {
                // Owner rule #8: a completed OR no-show resit sitting exhausts
                // the resit path — the record then falls into the retake lane.
                $hasConsumedResit = ExamResitAttempt::query()
                    ->where('academic_record_id', $academicRecord->id)
                    ->whereIn('status', [ExamResitAttempt::STATUS_COMPLETED, ExamResitAttempt::STATUS_NO_SHOW])
                    ->exists();

                if (! $hasConsumedResit) {
                    throw ValidationException::withMessages([
                        'failure_reason' => ['Sinh viên fail do điểm phải đi luồng thi lại, không đủ điều kiện học lại.'],
                    ]);
                }
            }

            // Cross-lane guard: block while a resit sitting is still pending on this
            // record — the resit path isn't exhausted yet.
            $hasInFlightResit = ExamResitAttempt::query()
                ->where('academic_record_id', $academicRecord->id)
                ->whereIn('status', ExamResitAttempt::IN_FLIGHT_STATUSES)
                ->exists();

            if ($hasInFlightResit) {
                throw ValidationException::withMessages([
                    'failure_reason' => ['Sinh viên đang có lượt thi lại chưa hoàn tất cho môn này, không thể đăng ký học lại.'],
                ]);
            }

            // Check for existing non-terminal registration (soft unique constraint)
            $existingActive = CourseRetakeRegistration::query()
                ->where('student_id', $data['student_id'])
                ->where('unit_id', $data['unit_id'])
                ->where('semester_id', $data['semester_id'])
                ->nonTerminal()
                ->exists();

            if ($existingActive) {
                throw ValidationException::withMessages([
                    'student_id' => ['Sinh viên đã có đăng ký học lại đang xử lý cho môn này trong kỳ này.'],
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
