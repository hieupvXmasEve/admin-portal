<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UpdateStudentEnglishLevelAction
{
    /**
     * Update a student's English level (0-5).
     * Enforces one-way rule: level can only increase.
     *
     * @param array $data {
     *     student_id: int,
     *     new_level: int (0-5),
     *     semester_id: int,
     *     trigger_source: ?string,
     *     notes: ?string,
     * }
     */
    public static function run(array $data): Student
    {
        $studentId = $data['student_id'];
        $newLevel = (int) $data['new_level'];
        $semesterId = $data['semester_id'];
        $userId = $data['created_by_user_id'] ?? Auth::id();
        $triggerSource = ProgressionTriggerSource::from($data['trigger_source'] ?? 'manual_admin');

        $student = Student::findOrFail($studentId);

        // Validate: student must be in intake_pre_uni_gc stage
        self::validateCanChangeLevel($student, $newLevel);

        $currentLevel = $student->gc_current_level ?? 0;

        return DB::transaction(function () use ($student, $currentLevel, $newLevel, $semesterId, $triggerSource, $userId, $data) {
            // Update student snapshot
            $student->update([
                'gc_current_level' => $newLevel,
            ]);

            // Create ENGLISH_LEVEL_CHANGED event
            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::ENGLISH_LEVEL_CHANGED,
                'semester_id' => $semesterId,
                'effective_at' => now(),
                'trigger_source' => $triggerSource,
                'created_by_user_id' => $userId,
                'from_english_level' => $currentLevel,
                'to_english_level' => $newLevel,
                'notes' => $data['notes'] ?? null,
            ]);

            Log::info('Student English level changed', [
                'student_id' => $student->id,
                'from_level' => $currentLevel,
                'to_level' => $newLevel,
                'trigger_source' => $triggerSource->value,
                'created_by' => $userId,
            ]);

            return $student->fresh();
        });
    }

    /**
     * Validate student can have their level changed.
     */
    private static function validateCanChangeLevel(Student $student, int $newLevel): void
    {
        // Must be in intake_pre_uni_gc stage
        if ($student->status !== 'intake_pre_uni_gc') {
            throw ValidationException::withMessages([
                'student_id' => 'Student must be in intake_pre_uni_gc stage to change English level.',
            ]);
        }

        // Validate level range
        if ($newLevel < 0 || $newLevel > 5) {
            throw ValidationException::withMessages([
                'new_level' => 'English level must be between 0 and 5.',
            ]);
        }

        $currentLevel = $student->gc_current_level ?? 0;

        // One-way rule: level can only increase
        if ($newLevel <= $currentLevel) {
            throw ValidationException::withMessages([
                'new_level' => sprintf(
                    'English level can only increase. Current level is %d, new level must be greater.',
                    $currentLevel
                ),
            ]);
        }
    }
}
