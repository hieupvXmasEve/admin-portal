<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\CourseRetakeRegistration;
use Illuminate\Database\Eloquent\Builder;

/**
 * A retake that is not cancelled still occupies its original academic record:
 * hide that record from both thi lại and học lại create lists, including enrolled.
 */
final class NonCancelledRetakeRegistration
{
    /**
     * @param  Builder<CourseRetakeRegistration>  $query
     * @return Builder<CourseRetakeRegistration>
     */
    public static function constrain(Builder $query): Builder
    {
        return $query->where('status', '!=', CourseRetakeRegistration::STATUS_CANCELLED);
    }
}
