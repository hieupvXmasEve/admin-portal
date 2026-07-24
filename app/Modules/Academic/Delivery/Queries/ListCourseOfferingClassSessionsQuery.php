<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Http\Resources\ClassSession\ClassSessionResource;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class ListCourseOfferingClassSessionsQuery
{
    /** @return array<string, mixed> */
    public function handle(CourseOffering $courseOffering): array
    {
        return ClassSessionResource::collection(app(ClassSessionService::class)->getClassSessions($courseOffering))->resolve();
    }
}
