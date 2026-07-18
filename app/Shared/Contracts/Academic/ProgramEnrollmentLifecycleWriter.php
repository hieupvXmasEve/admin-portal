<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface ProgramEnrollmentLifecycleWriter
{
    /**
     * @param  array{enrollment_status?: string, study_stage?: string|null, intake_major_semester_id?: int|null}  $changes
     */
    public function update(int $studentId, array $changes): void;
}
