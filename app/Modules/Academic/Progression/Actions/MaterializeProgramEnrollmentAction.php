<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Models\Student;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use Illuminate\Support\Facades\DB;

final class MaterializeProgramEnrollmentAction
{
    /**
     * @param  array{student_id: int}  $data
     */
    public static function run(array $data): ProgramEnrollment
    {
        return DB::transaction(function () use ($data): ProgramEnrollment {
            $legacyStudent = Student::query()
                ->whereKey($data['student_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $enrollment = ProgramEnrollment::query()
                ->where('source_type', ProgramEnrollment::LEGACY_STUDENT_SOURCE)
                ->where('source_id', $legacyStudent->id)
                ->lockForUpdate()
                ->first();

            $isExistingEnrollment = $enrollment !== null;
            if ($enrollment === null) {
                $enrollment = new ProgramEnrollment([
                    'source_type' => ProgramEnrollment::LEGACY_STUDENT_SOURCE,
                    'source_id' => $legacyStudent->id,
                    'is_primary' => true,
                ]);
            }

            $enrollmentStatus = $isExistingEnrollment
                ? $enrollment->enrollment_status
                : self::enrollmentStatus($legacyStudent);
            $studyStage = $isExistingEnrollment
                ? $enrollment->study_stage
                : self::studyStage($legacyStudent);

            if ($enrollmentStatus === 'active' && $enrollment->is_primary) {
                ProgramEnrollment::query()
                    ->where('student_id', $legacyStudent->id)
                    ->whereKeyNot($enrollment->id)
                    ->where('is_primary', true)
                    ->where('enrollment_status', 'active')
                    ->update([
                        'is_primary' => false,
                        'primary_active_student_id' => null,
                    ]);
            }

            $enrollment->fill([
                'student_id' => $legacyStudent->id,
                'program_id' => $legacyStudent->program_id,
                'curriculum_version_id' => $legacyStudent->curriculum_version_id,
                'intake_semester_id' => $legacyStudent->intake_semester_id,
                'intake_major_semester_id' => $isExistingEnrollment
                    ? $enrollment->intake_major_semester_id
                    : $legacyStudent->intake_major,
                'enrollment_status' => $enrollmentStatus,
                'study_stage' => $studyStage,
                'egc_starting_level' => $isExistingEnrollment
                    ? $enrollment->egc_starting_level
                    : $legacyStudent->gc_starting_level,
                'egc_current_level' => $isExistingEnrollment
                    ? $enrollment->egc_current_level
                    : $legacyStudent->gc_current_level,
                'egc_total_levels' => $isExistingEnrollment
                    ? $enrollment->egc_total_levels
                    : $legacyStudent->gc_total_levels,
                'source_snapshot' => self::sourceSnapshot($legacyStudent),
                'materialized_at' => now(),
            ]);
            $enrollment->save();

            return $enrollment;
        });
    }

    private static function enrollmentStatus(Student $student): string
    {
        return match ($student->status) {
            'deferred', 'admission_deferred' => 'deferred',
            'dropout' => 'dropout',
            'dropout_transfer' => 'dropout_transfer',
            'graduated' => 'graduated',
            'pending' => 'pending',
            default => $student->academic_status === 'withdrawn'
                ? 'dropout'
                : ($student->academic_status ?? 'active'),
        };
    }

    private static function studyStage(Student $student): ?string
    {
        if (in_array($student->status, ['intake_pre_uni_gc', 'intake_course', 'intake_major', 'pending_course_opening'], true)) {
            return $student->status;
        }

        if ($student->intake_major !== null) {
            return 'intake_major';
        }

        if ($student->intake_gc !== null) {
            return 'intake_pre_uni_gc';
        }

        return null;
    }

    /**
     * @return array<string, int|string|null>
     */
    private static function sourceSnapshot(Student $student): array
    {
        return [
            'program_id' => $student->program_id,
            'curriculum_version_id' => $student->curriculum_version_id,
            'intake_semester_id' => $student->intake_semester_id,
            'status' => $student->status,
            'academic_status' => $student->academic_status,
            'intake_gc' => $student->intake_gc,
            'intake_major' => $student->intake_major,
            'source_updated_at' => $student->updated_at?->toIso8601String(),
        ];
    }
}
