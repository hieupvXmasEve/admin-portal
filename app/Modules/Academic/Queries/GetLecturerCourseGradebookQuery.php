<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\CourseOffering;

/** @deprecated Resolve the Delivery query instead. */
class GetLecturerCourseGradebookQuery
{
    /** @return array<string, mixed> */
    public function handle(CourseOffering $courseOffering): array
    {
        return app(\App\Modules\Academic\Delivery\Queries\GetLecturerCourseGradebookQuery::class)->handle($courseOffering);
    }
}
