<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Shared\Contracts\Academic\CourseOfferingFinalizer;

final class FinalizeCourseOfferingAction
{
    /** @param array{course_offering_id:int, recalculate?:bool, dry_run?:bool, final_percentage_overrides?:array<int, float>} $data @return array<string, mixed> */
    public static function run(array $data): array
    {
        return app(CourseOfferingFinalizer::class)->finalize(
            $data['course_offering_id'],
            $data['recalculate'] ?? false,
            $data['dry_run'] ?? false,
            $data['final_percentage_overrides'] ?? [],
        );
    }
}
