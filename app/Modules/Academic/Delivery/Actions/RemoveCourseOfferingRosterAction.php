<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseRegistration;
use Illuminate\Support\Facades\DB;

final class RemoveCourseOfferingRosterAction
{
    /** @param array{course_offering_id: int} $data */
    public static function run(array $data): int
    {
        return DB::transaction(fn (): int => CourseRegistration::query()
            ->where('course_offering_id', $data['course_offering_id'])
            ->delete());
    }
}
