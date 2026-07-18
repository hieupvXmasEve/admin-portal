<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface InstructorAssignmentWriter
{
    public function assign(int $courseOfferingId, int $lecturerId): void;
}
