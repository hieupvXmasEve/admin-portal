<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class DeleteCourseOfferingClassSessionsAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): bool
    {
        $courseOffering = CourseOffering::query()->findOrFail($data['course_offering_id']);

        return app(ClassSessionService::class)->deleteClassSessions($courseOffering);
    }
}
