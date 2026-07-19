<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\Student;
use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class UpdateStudentEnglishLevelAction
{
    /** @param array{student_id:int, new_level:int, semester_id:int, trigger_source?:string, notes?:string, created_by_user_id?:int|null} $data */
    public static function run(array $data): Student
    {
        $student = Student::query()->findOrFail($data['student_id']);
        $userId = $data['created_by_user_id'] ?? Auth::id();
        $newLevel = (int) $data['new_level'];

        return DB::transaction(function () use ($data, $student, $userId, $newLevel): Student {
            if (! ProgramEnrollment::query()->where('student_id', $student->id)->exists()) {
                MaterializeProgramEnrollmentAction::run(['student_id' => (int) $student->id]);
            }
            $enrollment = ProgramEnrollment::query()
                ->where('student_id', $student->id)
                ->where('is_primary', true)
                ->lockForUpdate()
                ->firstOrFail();
            $currentLevel = $enrollment->egc_current_level ?? 0;

            if ($enrollment->study_stage !== 'intake_pre_uni_gc') {
                throw new InvalidProgressionState('student_id', 'Student must be in intake_pre_uni_gc stage to change English level.');
            }
            if ($newLevel < 0 || $newLevel > 5) {
                throw new InvalidProgressionState('new_level', 'English level must be between 0 and 5.');
            }
            if ($newLevel <= $currentLevel) {
                throw new InvalidProgressionState('new_level', sprintf('English level can only increase. Current level is %d, new level must be greater.', $currentLevel));
            }

            $enrollment->update(['egc_current_level' => $newLevel]);
            AcademicProgressionEvent::query()->create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED,
                'semester_id' => $data['semester_id'],
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::from($data['trigger_source'] ?? 'manual_admin'),
                'created_by_user_id' => $userId,
                'from_english_level' => $currentLevel,
                'to_english_level' => $newLevel,
                'notes' => $data['notes'] ?? null,
            ]);
            app(DomainEventPublisher::class)->publishAfterCommit(new DomainEvent(
                name: 'academic.english_level_changed',
                deduplicationKey: implode(':', ['academic.english_level_changed', 'student', $student->id, 'semester', $data['semester_id'], 'from', $currentLevel, 'to', $newLevel]),
                occurredAt: CarbonImmutable::now(),
                aggregateType: 'student',
                aggregateId: (string) $student->id,
                campusId: $student->campus_id,
                actorUserId: $userId,
                payload: ['student_id' => $student->id, 'from_level' => $currentLevel, 'to_level' => $newLevel],
            ));

            return $student->fresh();
        });
    }
}
