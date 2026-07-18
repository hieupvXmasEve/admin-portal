<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface ProgramEnrollmentWriter
{
    public function materialize(int $studentId): void;

    public function removeUnstarted(int $studentId): void;
}
