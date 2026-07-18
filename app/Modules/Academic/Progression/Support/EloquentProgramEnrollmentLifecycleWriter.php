<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Modules\Academic\Progression\Actions\UpdateProgramEnrollmentLifecycleAction;
use App\Shared\Contracts\Academic\ProgramEnrollmentLifecycleWriter;

final class EloquentProgramEnrollmentLifecycleWriter implements ProgramEnrollmentLifecycleWriter
{
    /**
     * @param  array{enrollment_status?: string, study_stage?: string|null, intake_major_semester_id?: int|null}  $changes
     */
    public function update(int $studentId, array $changes): void
    {
        UpdateProgramEnrollmentLifecycleAction::run([
            'student_id' => $studentId,
            ...$changes,
        ]);
    }
}
