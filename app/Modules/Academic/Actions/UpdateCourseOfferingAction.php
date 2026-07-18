<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\InstructorAssignmentWriter;
use Illuminate\Support\Facades\DB;

class UpdateCourseOfferingAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function run(CourseOffering $courseOffering, array $attributes): void
    {
        DB::transaction(function () use ($courseOffering, $attributes): void {
            $hasLectureAssignment = array_key_exists('lecture_id', $attributes);
            $lectureId = $attributes['lecture_id'] ?? null;
            unset($attributes['lecture_id']);

            $courseOffering->update($attributes);

            if (! $hasLectureAssignment) {
                return;
            }

            if ($lectureId === null) {
                $courseOffering->update(['lecture_id' => null]);

                return;
            }

            app(InstructorAssignmentWriter::class)->assign((int) $courseOffering->id, (int) $lectureId);
        });
    }
}
