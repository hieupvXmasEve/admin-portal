<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Enums\StudentActionType;
use App\Models\AcademicProgressionEvent;
use App\Models\IeltsCertificate;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Actions\PublishCourseStageChangedNotificationAction;
use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class TransitionToIntakeCourseAction
{
    /** @param array{student_id:int, semester_id:int, ielts_certificate_id:int, allow_missing_documents?:bool, notes?:string|null, created_by_user_id?:int|null} $data */
    public static function run(array $data): Student
    {
        $student = Student::query()->findOrFail($data['student_id']);
        $certificate = IeltsCertificate::query()->findOrFail($data['ielts_certificate_id']);
        $userId = $data['created_by_user_id'] ?? Auth::id();

        return DB::transaction(function () use ($data, $student, $certificate, $userId): Student {
            if (! ProgramEnrollment::query()->where('student_id', $student->id)->exists()) {
                MaterializeProgramEnrollmentAction::run(['student_id' => (int) $student->id]);
            }
            $enrollment = ProgramEnrollment::query()
                ->where('student_id', $student->id)
                ->where('is_primary', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ($enrollment->study_stage !== 'intake_pre_uni_gc') {
                throw new InvalidProgressionState('student_id', 'Student must be in intake_pre_uni_gc stage to transition to intake_course.');
            }
            if ($certificate->student_id !== $student->id) {
                throw new InvalidProgressionState('ielts_certificate_id', 'IELTS certificate does not belong to this student.');
            }
            if (! $certificate->meetsIntakeCourseRequirement()) {
                throw new InvalidProgressionState('ielts_certificate_id', 'IELTS score does not meet the intake-course requirement.');
            }
            if (! ($data['allow_missing_documents'] ?? false) && $certificate->missing_documents) {
                throw new InvalidProgressionState('ielts_certificate_id', 'IELTS certificate is missing required documents. Upload the scan before transitioning.');
            }

            $previousStage = $enrollment->study_stage;
            $notes = $data['notes'] ?? sprintf('Transitioned to intake_course based on IELTS score %s', $certificate->overall_score);
            $enrollment->update([
                'study_stage' => 'intake_course',
                'intake_major_semester_id' => $data['semester_id'],
                'egc_starting_level' => null,
                'egc_current_level' => null,
                'egc_total_levels' => null,
            ]);
            StudentActionLog::query()->create([
                'student_id' => $student->id,
                'action_type' => StudentActionType::STUDENT_MAJOR_ENROLLMENT->value,
                'reason' => 'Student officially enters major study.',
                'notes' => $notes,
                'changed_by_user_id' => $userId,
                'from_semester_id' => $data['semester_id'],
                'previous_status' => $previousStage,
                'new_status' => 'intake_course',
                'missing_documents' => $certificate->missing_documents,
            ]);
            AcademicProgressionEvent::query()->create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::COURSE_STAGE_CHANGED,
                'semester_id' => $data['semester_id'],
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::IELTS,
                'created_by_user_id' => $userId,
                'from_course_stage' => $previousStage,
                'to_course_stage' => 'intake_course',
                'ielts_certificate_id' => $certificate->id,
                'notes' => $notes,
            ]);

            app(PublishCourseStageChangedNotificationAction::class)->run(
                $student,
                $previousStage,
                'intake_course',
                (int) $data['semester_id'],
            );

            return $student->fresh();
        });
    }
}
