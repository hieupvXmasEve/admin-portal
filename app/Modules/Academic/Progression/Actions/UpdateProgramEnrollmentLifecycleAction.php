<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use Illuminate\Support\Facades\DB;

final class UpdateProgramEnrollmentLifecycleAction
{
    /**
     * @param  array{student_id: int, enrollment_status?: string, study_stage?: string|null, intake_major_semester_id?: int|null}  $data
     */
    public static function run(array $data): ProgramEnrollment
    {
        return DB::transaction(function () use ($data): ProgramEnrollment {
            $enrollment = ProgramEnrollment::query()
                ->where('student_id', $data['student_id'])
                ->where('is_primary', true)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($enrollment === null) {
                $enrollment = MaterializeProgramEnrollmentAction::run([
                    'student_id' => $data['student_id'],
                ]);
            }

            $changes = [];

            if (array_key_exists('enrollment_status', $data)) {
                $changes['enrollment_status'] = $data['enrollment_status'];
            }

            if (array_key_exists('study_stage', $data)) {
                $changes['study_stage'] = $data['study_stage'];
            }

            if (array_key_exists('intake_major_semester_id', $data)) {
                $changes['intake_major_semester_id'] = $data['intake_major_semester_id'];
            }

            $enrollment->update($changes);

            return $enrollment->fresh();
        });
    }
}
