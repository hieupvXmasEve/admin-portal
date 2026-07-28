<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\InstructorAssignmentWriter;
use Illuminate\Support\Facades\DB;

class CreateCourseOfferingAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function run(array $attributes): CourseOffering
    {
        return DB::transaction(function () use ($attributes): CourseOffering {
            $lectureId = $attributes['lecture_id'] ?? null;
            unset($attributes['lecture_id']);

            $courseOffering = CourseOffering::query()->create($attributes);

            if ($lectureId !== null) {
                app(InstructorAssignmentWriter::class)->assign((int) $courseOffering->id, (int) $lectureId);
            }

            return $courseOffering->fresh();
        });
    }
}
