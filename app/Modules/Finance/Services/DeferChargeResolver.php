<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\CourseRegistration;
use App\Models\DeferCase;
use App\Models\DeferCaseItem;
use App\Models\Student;
use App\Modules\Finance\Support\DeferChargePolicy;

class DeferChargeResolver
{
    public function findApplicableFullCase(Student $student, int $semesterId): ?DeferCase
    {
        $cases = DeferCase::query()
            ->where('student_id', $student->id)
            ->where('fee_policy', DeferCase::POLICY_PRESERVE)
            ->where('scope_type', DeferCase::SCOPE_FULL)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->get();

        return $cases->first(function (DeferCase $case) use ($semesterId) {
            return DeferChargePolicy::shouldSkipFullSemester($case->toArray(), $semesterId);
        });
    }

    public function markFullCaseApplied(DeferCase $deferCase, int $semesterId): void
    {
        $deferCase->update([
            'applied_at' => now(),
            'applied_semester_id' => $semesterId,
        ]);
    }

    public function findApplicableCourseItem(CourseRegistration $registration): ?DeferCaseItem
    {
        $referenceId = $registration->original_registration_id ?? $registration->id;

        if (! $referenceId) {
            return null;
        }

        $items = DeferCaseItem::query()
            ->where('course_registration_id', $referenceId)
            ->whereHas('deferCase', function ($query) use ($registration) {
                $query->where('student_id', $registration->student_id)
                    ->where('fee_policy', DeferCase::POLICY_PRESERVE)
                    ->where('scope_type', DeferCase::SCOPE_COURSES);
            })
            ->with('deferCase')
            ->get();

        return $items->first(function (DeferCaseItem $item) use ($registration) {
            return DeferChargePolicy::shouldSkipCourse(
                $item->deferCase?->toArray() ?? [],
                $item->toArray(),
                $registration->semester_id
            );
        });
    }

    public function markItemApplied(DeferCaseItem $item, int $semesterId): void
    {
        $item->update([
            'applied_at' => now(),
            'applied_semester_id' => $semesterId,
        ]);
    }
}
