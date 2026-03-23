<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Placement;

use App\Modules\Academic\Actions\PublishCourseStageChangedNotificationAction;
use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\IeltsCertificate;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TransitionToIntakeCourseAction
{
    /**
     * Transition a student from intake_pre_uni_gc to intake_course.
     * Requires IELTS >= 6.5 and (by default) complete documents.
     *
     * @param array $data {
     *     student_id: int,
     *     semester_id: int,
     *     ielts_certificate_id: int,
     *     allow_missing_documents: ?bool (default false),
     *     notes: ?string,
     * }
     */
    public static function run(array $data): Student
    {
        $studentId = $data['student_id'];
        $semesterId = (int) $data['semester_id'];
        $ieltsCertificateId = $data['ielts_certificate_id'];
        $allowMissingDocuments = $data['allow_missing_documents'] ?? false;
        $userId = $data['created_by_user_id'] ?? Auth::id();

        $student = Student::findOrFail($studentId);
        $certificate = IeltsCertificate::findOrFail($ieltsCertificateId);

        // Validate transition requirements
        self::validateCanTransition($student, $certificate, $allowMissingDocuments);

        $previousStage = $student->status;

        return DB::transaction(function () use ($student, $certificate, $previousStage, $semesterId, $userId, $data) {
            // Update student snapshot
            $student->update([
                'status' => 'intake_course',
                'status_change_date' => now()->toDateString(),
                'status_reason' => $data['notes'] ?? sprintf(
                    'Transitioned to intake_course based on IELTS score %s',
                    $certificate->overall_score
                ),
                'status_changed_by' => $userId,
                'intake_major' => $semesterId,
            ]);

            // Create COURSE_STAGE_CHANGED event
            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::COURSE_STAGE_CHANGED,
                'semester_id' => $semesterId,
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::IELTS,
                'created_by_user_id' => $userId,
                'from_course_stage' => $previousStage,
                'to_course_stage' => 'intake_course',
                'ielts_certificate_id' => $certificate->id,
                'notes' => $data['notes'] ?? sprintf(
                    'Transitioned to intake_course based on IELTS score %s',
                    $certificate->overall_score
                ),
            ]);

            Log::info('Student transitioned to intake_course', [
                'student_id' => $student->id,
                'from_stage' => $previousStage,
                'ielts_certificate_id' => $certificate->id,
                'ielts_score' => $certificate->overall_score,
                'created_by' => $userId,
            ]);

            app(PublishCourseStageChangedNotificationAction::class)->run(
                $student,
                $previousStage,
                'intake_course',
                $semesterId
            );

            return $student->fresh();
        });
    }

    /**
     * Validate student can transition to intake_course.
     */
    private static function validateCanTransition(
        Student $student,
        IeltsCertificate $certificate,
        bool $allowMissingDocuments
    ): void {
        // Must be in intake_pre_uni_gc stage
        if ($student->status !== 'intake_pre_uni_gc') {
            throw ValidationException::withMessages([
                'student_id' => 'Student must be in intake_pre_uni_gc stage to transition to intake_course.',
            ]);
        }

        // Certificate must belong to this student
        if ($certificate->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'ielts_certificate_id' => 'IELTS certificate does not belong to this student.',
            ]);
        }

        // IELTS score must meet threshold
        if (! $certificate->meetsIntakeCourseRequirement()) {
            throw ValidationException::withMessages([
                'ielts_certificate_id' => sprintf(
                    'IELTS score must be at least %s to transition to intake_course. Current score: %s',
                    IeltsCertificate::SCORE_THRESHOLD_INTAKE_COURSE,
                    $certificate->overall_score
                ),
            ]);
        }

        // During ongoing learning, documents are required by default
        if (! $allowMissingDocuments && $certificate->missing_documents) {
            throw ValidationException::withMessages([
                'ielts_certificate_id' => 'IELTS certificate is missing required documents. Upload the scan before transitioning.',
            ]);
        }
    }
}
