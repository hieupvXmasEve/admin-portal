<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseOffering;

class UpdateCourseOfferingAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function run(CourseOffering $courseOffering, array $attributes): void
    {
        $courseOffering->update($attributes);
    }
}
