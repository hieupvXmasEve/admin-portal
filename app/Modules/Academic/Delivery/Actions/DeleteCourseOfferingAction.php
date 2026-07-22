<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingDeletionException;
use Illuminate\Support\Facades\DB;

final class DeleteCourseOfferingAction
{
    /** @param array{course_offering_id: int, campus_id: int} $data */
    public static function run(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $courseOffering = CourseOffering::query()
                ->whereKey($data['course_offering_id'])
                ->where('campus_id', $data['campus_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($courseOffering->isCourseCompleted()) {
                throw new CourseOfferingDeletionException('Cannot delete a completed course. You can only view or duplicate it.');
            }

            $scheduledSessionsCount = $courseOffering->classSessions()->count();
            $enrolledStudentsCount = (int) $courseOffering->current_enrollment;

            if ($scheduledSessionsCount > 0 || $enrolledStudentsCount > 0) {
                $reasons = [];
                if ($scheduledSessionsCount > 0) {
                    $reasons[] = "{$scheduledSessionsCount} scheduled session(s)";
                }
                if ($enrolledStudentsCount > 0) {
                    $reasons[] = "{$enrolledStudentsCount} enrolled student(s)";
                }

                throw new CourseOfferingDeletionException(
                    'Cannot delete course offering because it has '.implode(' and ', $reasons).'. Please cancel the course offering instead or remove all sessions and students first.',
                );
            }

            $registrationCount = RemoveCourseOfferingRosterAction::run([
                'course_offering_id' => (int) $courseOffering->id,
            ]);

            $courseOffering->delete();

            return $registrationCount;
        });
    }
}
