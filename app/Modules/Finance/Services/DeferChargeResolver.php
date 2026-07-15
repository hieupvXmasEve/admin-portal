<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\DeferCase;
use App\Models\DeferCaseItem;
use App\Models\Student;
use App\Modules\Finance\Support\DeferChargePolicy;
use Illuminate\Support\Facades\DB;

class DeferChargeResolver
{
    /**
     * Whether the student's enrollment in this semester is fully deferred and so
     * non-billable for batch charge generation/preview.
     *
     * FIN-REV-020-02 (M2): a FULL-scope defer marks every active registration
     * registration_status = 'defer' (PRESERVE and FORFEIT alike), leaving no
     * billable registration in the semester. Generation/preview key off that
     * directly so a voided obligation is never resurrected, regardless of fee
     * policy. This replaces the old "PRESERVE = skip future charge" branch,
     * which governed re-enrollment via applies_once/applied_at; re-enrollment
     * billing now flows normally because re-enrolled students hold new,
     * non-defer registrations. A COURSE-scope (partial) defer leaves other
     * registrations billable, so it does NOT suppress the semester's term
     * charges here (course-level handling stays report-only).
     */
    public function isSemesterEnrollmentDeferred(Student $student, int $semesterId): bool
    {
        $statuses = DB::table('course_registrations')
            ->where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->pluck('registration_status');

        if (! $statuses->contains('defer')) {
            return false;
        }

        return $statuses->intersect(['pending', 'registered', 'confirmed'])->isEmpty();
    }

    public function findApplicableCourseItem(object $registration): ?DeferCaseItem
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
