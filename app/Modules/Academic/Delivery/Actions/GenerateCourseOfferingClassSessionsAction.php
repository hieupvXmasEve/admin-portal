<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\ClassSessionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class GenerateCourseOfferingClassSessionsAction
{
    /** @param array<string, mixed> $data @return Collection<int, mixed> */
    public static function run(array $data): Collection
    {
        $courseOffering = CourseOffering::query()->findOrFail($data['course_offering_id']);

        return app(ClassSessionService::class)->generateClassSessions(
            $courseOffering,
            $data['room_id'],
            isset($data['start_date']) ? Carbon::parse($data['start_date']) : null,
            $data['weekly_schedule'],
            $data['excluded_dates'] ?? [],
        );
    }
}
