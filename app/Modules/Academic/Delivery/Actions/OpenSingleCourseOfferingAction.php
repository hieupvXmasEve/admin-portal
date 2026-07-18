<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use Illuminate\Support\Facades\DB;

final class OpenSingleCourseOfferingAction
{
    /**
     * @param  array{semester_id: int, campus_id: int, attributes: array<string, mixed>}  $data
     */
    public static function run(array $data): ?CourseOffering
    {
        return DB::transaction(function () use ($data): ?CourseOffering {
            $attributes = $data['attributes'];
            $existingOffering = CourseOffering::query()
                ->where('semester_id', $data['semester_id'])
                ->where('unit_id', $attributes['unit_id'])
                ->where('section_code', $attributes['section_code'] ?? null)
                ->lockForUpdate()
                ->first();

            if ($existingOffering !== null) {
                return null;
            }

            $lectureId = $attributes['lecture_id'] ?? null;
            unset($attributes['lecture_id']);

            $courseOffering = CourseOffering::query()->create([
                ...$attributes,
                'semester_id' => $data['semester_id'],
                'campus_id' => $data['campus_id'],
                'current_enrollment' => 0,
                'waitlist_capacity' => $attributes['waitlist_capacity'] ?? 10,
                'current_waitlist' => 0,
                'is_active' => true,
                'enrollment_status' => 'open',
            ]);

            if ($lectureId !== null) {
                AssignInstructorAction::run([
                    'course_offering_id' => (int) $courseOffering->id,
                    'lecture_id' => (int) $lectureId,
                ]);
            }

            return $courseOffering;
        });
    }
}
