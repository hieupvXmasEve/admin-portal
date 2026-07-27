<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\CourseOffering;

/**
 * Distinct unit types taught at a campus, so non-Delivery Academic pages
 * reach CourseOffering through a Delivery-owned seam instead of importing
 * the shared model directly.
 */
class GetCourseOfferingUnitTypesQuery
{
    /**
     * @return list<string>
     */
    public function handle(int $campusId): array
    {
        return CourseOffering::query()
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_offerings.campus_id', $campusId)
            ->whereNotNull('units.unit_type')
            ->distinct()
            ->orderBy('units.unit_type')
            ->pluck('units.unit_type')
            ->all();
    }
}
