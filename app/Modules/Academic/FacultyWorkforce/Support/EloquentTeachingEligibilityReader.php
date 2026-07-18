<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Support;

use App\Models\Lecture;
use App\Shared\Contracts\Academic\DTO\CourseOfferingUnitReference;
use App\Shared\Contracts\Academic\DTO\TeachingEligibility;
use App\Shared\Contracts\Academic\TeachingEligibilityReader;

final readonly class EloquentTeachingEligibilityReader implements TeachingEligibilityReader
{
    public function __construct(private FacultyTeachingEligibilityResolver $resolver) {}

    public function forLecturerAndUnit(int $lecturerId, CourseOfferingUnitReference $unit): ?TeachingEligibility
    {
        $lecturer = Lecture::query()->find($lecturerId);

        return $lecturer === null ? null : $this->resolver->resolve($lecturer, $unit);
    }
}
