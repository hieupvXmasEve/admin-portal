<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Enums\StudentActionType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Detects deferred students who still retain course registrations for the semester.
 */
final class BillingExceptionDeferredEnrollmentMatcher
{
    public static function applyExistsConstraint(Builder $query, ?int $filterSemesterId = null): void
    {
        $query->select(DB::raw(1))
            ->from('students as defer_students')
            ->whereColumn('defer_students.id', 'course_registrations.student_id')
            ->where('defer_students.status', 'deferred')
            ->where(function ($deferEvidenceQuery) use ($filterSemesterId): void {
                $deferEvidenceQuery
                    ->whereExists(function ($caseQuery) use ($filterSemesterId): void {
                        $caseQuery->select(DB::raw(1))
                            ->from('defer_cases')
                            ->whereColumn('defer_cases.student_id', 'defer_students.id')
                            ->whereColumn('defer_cases.semester_id', 'course_registrations.semester_id');

                        if ($filterSemesterId !== null) {
                            $caseQuery->where('defer_cases.semester_id', $filterSemesterId);
                        }
                    })
                    ->orWhereExists(function ($logQuery) use ($filterSemesterId): void {
                        $logQuery->select(DB::raw(1))
                            ->from('student_action_logs')
                            ->whereColumn('student_action_logs.student_id', 'defer_students.id')
                            ->where('student_action_logs.action_type', StudentActionType::ACADEMIC_DEFER->value)
                            ->whereColumn('student_action_logs.from_semester_id', 'course_registrations.semester_id');

                        if ($filterSemesterId !== null) {
                            $logQuery->where('student_action_logs.from_semester_id', $filterSemesterId);
                        }
                    });
            });
    }
}