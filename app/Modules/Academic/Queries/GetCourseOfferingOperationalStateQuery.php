<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\CourseOffering;
use App\Models\User;

/** @deprecated Resolve the Delivery query instead. */
class GetCourseOfferingOperationalStateQuery
{
    /** @return array<string, mixed> */
    public static function handle(CourseOffering $courseOffering, User $user): array
    {
        return app(\App\Modules\Academic\Delivery\Queries\GetCourseOfferingOperationalStateQuery::class)->handle($courseOffering, $user);
    }
}
