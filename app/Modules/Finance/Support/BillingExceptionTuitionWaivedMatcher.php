<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Detects students whose tuition plan assigns a zero-amount term for the
 * registration semester (waived / no charge expected).
 */
final class BillingExceptionTuitionWaivedMatcher
{
    public static function applyExistsConstraint(Builder $query, ?int $filterSemesterId = null): void
    {
        $query->select(DB::raw(1))
            ->from('students as waiver_students')
            ->join('semesters as waiver_target_sem', function ($join) use ($filterSemesterId): void {
                $join->whereColumn('waiver_target_sem.id', 'course_registrations.semester_id');

                if ($filterSemesterId !== null) {
                    $join->where('waiver_target_sem.id', $filterSemesterId);
                }
            })
            ->join('semesters as waiver_intake_sem', 'waiver_intake_sem.id', '=', 'waiver_students.intake_semester_id')
            ->join('semesters as waiver_intake_major_sem', 'waiver_intake_major_sem.id', '=', 'waiver_students.intake_major')
            ->join('tuition_plans as waiver_plans', function ($join): void {
                $join->on('waiver_plans.curriculum_version_id', '=', 'waiver_students.curriculum_version_id')
                    ->on('waiver_plans.intake_semester_id', '=', 'waiver_students.intake_semester_id');
            })
            ->join('tuition_plan_terms as waiver_terms', function ($join): void {
                $join->on('waiver_terms.tuition_plan_id', '=', 'waiver_plans.id')
                    ->whereRaw('waiver_terms.term_number = (
                        SELECT COUNT(*)
                        FROM semesters waiver_term_semesters
                        WHERE waiver_term_semesters.start_date >= waiver_intake_major_sem.start_date
                          AND waiver_term_semesters.start_date <= waiver_target_sem.start_date
                    )');
            })
            ->whereColumn('waiver_students.id', 'course_registrations.student_id')
            ->whereColumn('waiver_target_sem.start_date', '>=', 'waiver_intake_sem.start_date')
            ->whereColumn('waiver_target_sem.start_date', '>=', 'waiver_intake_major_sem.start_date')
            ->whereNull('waiver_students.deleted_at')
            ->where('waiver_terms.amount', 0);
    }
}