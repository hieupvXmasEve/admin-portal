<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\CourseResult;

interface CourseResultTranscriptWriter
{
    /** @param list<CourseResult> $courseResults */
    public function commit(array $courseResults): void;
}
