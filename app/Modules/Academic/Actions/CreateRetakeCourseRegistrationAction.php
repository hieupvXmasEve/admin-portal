<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRetakeCourseRegistrationAction
{
    /**
     * Create a new retake course registration and automatically create a FinanceCharge.
     *
     * Flow: validate → create registration (approved) → create FinanceCharge →
     *       transition registration to payment_pending.
     * Finance team can create a DNG payment request separately via /finance/retake-course if needed.
     *
     * @param  array{
     *   student_id: int,
     *   unit_id: int,
     *   original_academic_record_id: int,
     *   course_offering_id: int,
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
            $student = Student::findOrFail($data['student_id']);
            $unit = Unit::findOrFail($data['unit_id']);
            $courseOffering = CourseOffering::findOrFail($data['course_offering_id']);

            // Validate unit has retake_fee configured
            if (! $unit->retake_fee || (float) $unit->retake_fee <= 0) {
                throw ValidationException::withMessages([
                    'unit_id' => ["Môn {$unit->code} chưa cấu hình phí học lại (retake_fee = 0). Vui lòng cập nhật phí học lại trong quản lý Unit trước khi đăng ký."],
                ]);
            }

            // Validate student is intake_course
            if ($student->status !== 'intake_course') {
                throw ValidationException::withMessages([
                    'student_id' => ['Sinh viên phải ở trạng thái intake_course để đăng ký học lại.'],
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

            // Calculate attempt_number: count existing academic records + 1
            $previousAttempts = AcademicRecord::query()
                ->where('student_id', $data['student_id'])
                ->where('unit_id', $data['unit_id'])
                ->count();
            $attemptNumber = $previousAttempts + 1;

            // Snapshot retake_fee from unit
            $retakeFee = $unit->retake_fee ?? 0;

            $registration = CourseRetakeRegistration::create([
                'student_id' => $data['student_id'],
                'unit_id' => $data['unit_id'],
                'original_academic_record_id' => $data['original_academic_record_id'],
                'course_offering_id' => $data['course_offering_id'],
                'semester_id' => $data['semester_id'],
                'campus_id' => $data['campus_id'],
                'status' => CourseRetakeRegistration::STATUS_APPROVED,
                'attempt_number' => $attemptNumber,
                'retake_fee' => $retakeFee,
                'registration_start_date' => $data['registration_start_date'] ?? null,
                'registration_end_date' => $data['registration_end_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'approved_by_user_id' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Automatically create a FinanceCharge (no DNG request).
            // Finance team can create the DNG payment request later via /finance/retake-course.
            $charge = app(CreateFinanceChargeAction::class)->handle([
                'student_id' => $registration->student_id,
                'semester_id' => $registration->semester_id,
                'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
                'amount' => $retakeFee,
                'description' => "Phí học lại: {$unit->code} - {$unit->name}",
                'source_type' => CourseRetakeRegistration::class,
                'source_id' => $registration->id,
                'created_by_user_id' => auth()->id(),
            ]);

            $registration->transitionToPaymentPending($charge->id, auth()->id());

            return $registration->fresh();
        });
    }
}
