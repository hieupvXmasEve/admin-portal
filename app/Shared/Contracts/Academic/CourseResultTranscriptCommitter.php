<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface CourseResultTranscriptCommitter
{
    public function commitForCourseOffering(int $courseOfferingId): void;
}
