<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Enums\StudentActionType;
use App\Models\IeltsCertificate;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class InitializeStudentPlacementAction
{
    /** @param array{student_id:int, semester_id:int, has_ielts?:bool, ielts_score?:float, upload_record_id?:int|null, missing_documents?:bool, english_level?:int, trigger_source?:string, notes?:string, created_by_user_id?:int|null, issue_date?:string|null, ielts_notes?:string|null} $data */
    public static function run(array $data): Student
    {
        $student = Student::query()->findOrFail($data['student_id']);
        $userId = $data['created_by_user_id'] ?? Auth::id();

        return DB::transaction(function () use ($data, $student, $userId): Student {
            MaterializeProgramEnrollmentAction::run(['student_id' => (int) $student->id]);
            // Highest id wins among multiple is_primary rows (binding
            // tie-break convention, see StudentLifecycleProjection).
            $enrollment = ProgramEnrollment::query()
                ->where('student_id', $student->id)
                ->where('is_primary', true)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->firstOrFail();

            if ($enrollment->study_stage !== null || AcademicProgressionEvent::query()
                ->where('student_id', $student->id)
                ->where('event_type', AcademicProgressionEventType::PLACEMENT_INITIALIZED)
                ->exists()) {
                throw new InvalidProgressionState('student_id', 'Student has already been placed. Use update actions instead.');
            }

            $hasIelts = (bool) ($data['has_ielts'] ?? false);
            $certificate = null;
            $stage = 'intake_pre_uni_gc';
            $level = (int) ($data['english_level'] ?? 0);
            $trigger = ProgressionTriggerSource::from($data['trigger_source'] ?? 'manual_admin');

            if ($hasIelts) {
                $certificate = IeltsCertificate::query()->create([
                    'student_id' => $student->id,
                    'overall_score' => (float) $data['ielts_score'],
                    'submitted_at' => now(),
                    'upload_record_id' => $data['upload_record_id'] ?? null,
                    'missing_documents' => empty($data['upload_record_id']) || (bool) ($data['missing_documents'] ?? false),
                    'issue_date' => $data['issue_date'] ?? null,
                    'notes' => $data['ielts_notes'] ?? null,
                ]);
                $trigger = ProgressionTriggerSource::IELTS;
                if ($certificate->meetsIntakeCourseRequirement()) {
                    $stage = 'intake_course';
                    $level = 0;
                }

                AcademicProgressionEvent::query()->create([
                    'student_id' => $student->id,
                    'event_type' => AcademicProgressionEventType::IELTS_RECORDED,
                    'semester_id' => $data['semester_id'],
                    'effective_at' => now(),
                    'trigger_source' => ProgressionTriggerSource::IELTS,
                    'created_by_user_id' => $userId,
                    'ielts_certificate_id' => $certificate->id,
                    'notes' => sprintf('IELTS score %s recorded at placement', $certificate->overall_score),
                ]);
            } else {
                $trigger = ProgressionTriggerSource::PLACEMENT_TEST;
            }

            $enrollment->update([
                'enrollment_status' => 'active',
                'study_stage' => $stage,
                'egc_starting_level' => $stage === 'intake_pre_uni_gc' ? $level : null,
                'egc_current_level' => $stage === 'intake_pre_uni_gc' ? $level : null,
                'egc_total_levels' => $stage === 'intake_pre_uni_gc' ? ($enrollment->egc_total_levels ?? 6) : null,
            ]);

            // previous_status 'pending' is the reporting convention used by the
            // historical backfill (students:create-egc-action-logs), not the live
            // students.status value — kept identical so reporting stays uniform.
            StudentActionLog::query()->create([
                'student_id' => $student->id,
                'action_type' => StudentActionType::STUDENT_ENROLLMENT_NE->value,
                'reason' => $stage === 'intake_pre_uni_gc'
                    ? 'Student completes NE enrollment.'
                    : 'Student completes enrollment and directly enters course stage.',
                'notes' => $data['notes'] ?? null,
                'changed_by_user_id' => $userId,
                'from_semester_id' => $data['semester_id'],
                'previous_status' => 'pending',
                'new_status' => $stage,
                'missing_documents' => false,
            ]);

            AcademicProgressionEvent::query()->create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::PLACEMENT_INITIALIZED,
                'semester_id' => $data['semester_id'],
                'effective_at' => now(),
                'trigger_source' => $trigger,
                'created_by_user_id' => $userId,
                'to_course_stage' => $stage,
                'to_english_level' => $stage === 'intake_pre_uni_gc' ? $level : null,
                'ielts_certificate_id' => $certificate?->id,
                'notes' => $data['notes'] ?? null,
            ]);
            app(DomainEventPublisher::class)->publishAfterCommit(new DomainEvent(
                name: 'academic.placement_initialized',
                deduplicationKey: implode(':', ['academic.placement_initialized', 'student', $student->id, 'semester', $data['semester_id'], 'stage', $stage, 'level', $level]),
                occurredAt: CarbonImmutable::now(),
                aggregateType: 'student',
                aggregateId: (string) $student->id,
                campusId: $student->campus_id,
                actorUserId: $userId,
                payload: ['student_id' => $student->id, 'study_stage' => $stage, 'english_level' => $level],
            ));

            return $student->fresh();
        });
    }
}
