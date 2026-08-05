<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EloquentStudentCollectionEligibilityReader implements StudentCollectionEligibilityReader
{
    /**
     * `students.status` is a legacy column that program-enrollment transitions
     * don't write back to (see program_enrollments.study_stage). Match against
     * the live enrollment projection instead, so a student who has actually
     * progressed to intake_course/intake_major isn't silently dropped from the
     * tuition/EGC candidate pool by a stale column. Fall back to the legacy
     * column only for students who have no materialized program_enrollments
     * row yet (materialization is lazy).
     *
     * @param  list<'egc'|'tuition'>  $collectionPurposes
     * @return list<int>
     */
    public function eligibleStudentIds(array $collectionPurposes, ?int $campusId): array
    {
        $statuses = match ($collectionPurposes) {
            ['egc'] => ['intake_pre_uni_gc'],
            ['tuition'] => ['intake_course', 'intake_major'],
            default => Student::FINANCIAL_STATUSES,
        };

        return Student::query()
            ->when($campusId !== null, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->where(function (Builder $outer) use ($statuses): void {
                $outer->whereExists(fn (QueryBuilder $sub) => self::materializedFinancialSubquery($sub, $statuses))
                    ->orWhere(function (Builder $legacy) use ($statuses): void {
                        $legacy->whereNotExists(fn (QueryBuilder $sub) => self::primaryEnrollmentSubquery($sub))
                            ->whereIn('status', $statuses);
                    });
            })
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    private static function primaryEnrollmentSubquery(QueryBuilder $sub): QueryBuilder
    {
        return $sub->from('program_enrollments')
            ->whereColumn('program_enrollments.student_id', 'students.id')
            ->where('program_enrollments.is_primary', true);
    }

    /**
     * @param  list<string>  $statuses
     */
    private static function materializedFinancialSubquery(QueryBuilder $sub, array $statuses): QueryBuilder
    {
        return self::primaryEnrollmentSubquery($sub)
            ->where('program_enrollments.enrollment_status', 'active')
            ->whereIn('program_enrollments.study_stage', $statuses);
    }
}
