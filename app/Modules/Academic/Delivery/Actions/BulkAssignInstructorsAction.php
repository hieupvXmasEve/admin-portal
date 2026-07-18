<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use Illuminate\Support\Facades\DB;

final class BulkAssignInstructorsAction
{
    /**
     * @param  array{campus_id: int, assignments: list<array{course_offering_id: int, lecture_id: int}>}  $data
     */
    public static function run(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $assignmentsCount = 0;
            foreach ($data['assignments'] as $assignment) {
                $courseOffering = CourseOffering::query()
                    ->whereKey($assignment['course_offering_id'])
                    ->where('campus_id', $data['campus_id'])
                    ->first();
                if ($courseOffering === null || $courseOffering->lecture_id !== null) {
                    continue;
                }

                AssignInstructorAction::run($assignment);
                $assignmentsCount++;
            }

            return $assignmentsCount;
        });
    }
}
