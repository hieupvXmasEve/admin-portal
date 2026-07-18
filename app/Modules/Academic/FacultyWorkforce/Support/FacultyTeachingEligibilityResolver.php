<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Support;

use App\Models\Lecture;
use App\Shared\Contracts\Academic\DTO\CourseOfferingUnitReference;
use App\Shared\Contracts\Academic\DTO\TeachingEligibility;

final class FacultyTeachingEligibilityResolver
{
    public function resolve(Lecture $lecturer, CourseOfferingUnitReference $unit): TeachingEligibility
    {
        $reason = $this->reason($lecturer, $unit);

        return new TeachingEligibility(
            lecturerId: (int) $lecturer->id,
            campusId: (int) $lecturer->campus_id,
            maxTeachingHoursPerWeek: $lecturer->max_teaching_hours_per_week === null
                ? null
                : (int) $lecturer->max_teaching_hours_per_week,
            isEligible: $reason === 'eligible',
            reason: $reason,
        );
    }

    private function reason(Lecture $lecturer, CourseOfferingUnitReference $unit): string
    {
        if (! $lecturer->is_active) {
            return 'ineligible_faculty_inactive';
        }

        if ($lecturer->employment_status !== 'active') {
            return 'ineligible_employment_status';
        }

        if ($lecturer->employment_type === 'contract') {
            if ($lecturer->contract_start_date !== null && $lecturer->contract_start_date->isAfter(today())) {
                return 'ineligible_contract_not_started';
            }

            if ($lecturer->contract_end_date !== null && $lecturer->contract_end_date->isBefore(today())) {
                return 'ineligible_contract_expired';
            }
        }

        if (! $lecturer->is_available_for_assignment) {
            return 'ineligible_assignment_unavailable';
        }

        if (! $this->hasUnitExpertise($lecturer, $unit)) {
            return 'ineligible_unit_expertise';
        }

        return 'eligible';
    }

    private function hasUnitExpertise(Lecture $lecturer, CourseOfferingUnitReference $unit): bool
    {
        $expertise = array_filter($lecturer->expertise_areas ?? []);

        // Existing Faculty records can lack structured expertise. Preserve their
        // historical assignability until Workforce data is completed.
        if ($expertise === []) {
            return true;
        }

        $unitDescription = strtolower($unit->code.' '.$unit->name);

        foreach ($expertise as $area) {
            $area = strtolower(trim((string) $area));
            if ($area !== '' && str_contains($unitDescription, $area)) {
                return true;
            }
        }

        return false;
    }
}
