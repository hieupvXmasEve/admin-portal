<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\CourseOfferingSurveyContextReader;
use App\Shared\Contracts\Academic\DTO\CourseOfferingSurveyContext;

final class EloquentCourseOfferingSurveyContextReader implements CourseOfferingSurveyContextReader
{
    public function forOffering(int $courseOfferingId): CourseOfferingSurveyContext
    {
        $courseOffering = CourseOffering::query()
            ->with(['unit:id,unit_type', 'semester:id,end_date'])
            ->findOrFail($courseOfferingId);

        return new CourseOfferingSurveyContext(
            courseOfferingId: (int) $courseOffering->id,
            campusId: (int) $courseOffering->campus_id,
            semesterId: $courseOffering->semester_id === null ? null : (int) $courseOffering->semester_id,
            unitType: $courseOffering->unit?->unit_type,
            semesterEndDate: $courseOffering->semester?->end_date?->toDateString(),
        );
    }
}
