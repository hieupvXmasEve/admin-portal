<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Modules\Academic\Progression\Actions\MaterializeProgramEnrollmentAction;
use App\Modules\Academic\Progression\Actions\RemoveUnstartedProgramEnrollmentAction;
use App\Shared\Contracts\Academic\ProgramEnrollmentWriter;

final class EloquentProgramEnrollmentWriter implements ProgramEnrollmentWriter
{
    public function materialize(int $studentId): void
    {
        MaterializeProgramEnrollmentAction::run(['student_id' => $studentId]);
    }

    public function removeUnstarted(int $studentId): void
    {
        RemoveUnstartedProgramEnrollmentAction::run(['student_id' => $studentId]);
    }
}
