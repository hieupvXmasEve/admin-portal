<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Engagement;

use App\Shared\Contracts\Engagement\DTO\CourseSurveyTarget;

interface CourseSurveyTargetReader
{
    public function forCourseOffering(int $courseOfferingId): ?CourseSurveyTarget;
}
