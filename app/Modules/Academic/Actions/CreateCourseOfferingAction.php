<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseOffering;

class CreateCourseOfferingAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function run(array $attributes): CourseOffering
    {
        return CourseOffering::query()->create($attributes);
    }
}
