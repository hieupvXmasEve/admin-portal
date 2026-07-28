<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Academic\ProgramEnrollmentStatusFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class FilterStudentsByProgramEnrollmentStatus implements ProgramEnrollmentStatusFilter
{
    /**
     * @param  list<string>  $statuses
     */
    public function apply(Builder $students, array $statuses): void
    {
        $statuses = array_values(array_unique(array_filter($statuses)));
        if ($statuses === []) {
            return;
        }

        $students->where(function (Builder $query) use ($statuses): void {
            $query->whereExists(function ($enrollments) use ($statuses): void {
                $enrollments->selectRaw('1')
                    ->from((new ProgramEnrollment)->getTable().' as lifecycle_enrollments')
                    ->whereColumn('lifecycle_enrollments.student_id', 'students.id')
                    ->where('lifecycle_enrollments.is_primary', true)
                    ->where(function ($statusQuery) use ($statuses): void {
                        $this->applyEnrollmentStatuses($statusQuery, $statuses);
                    });
            })->orWhere(function (Builder $legacy) use ($statuses): void {
                $legacy->whereNotExists(function ($enrollments): void {
                    $enrollments->selectRaw('1')
                        ->from((new ProgramEnrollment)->getTable().' as any_enrollments')
                        ->whereColumn('any_enrollments.student_id', 'students.id');
                })->whereIn('students.status', $statuses);
            });
        });
    }

    /** @param list<string> $statuses */
    private function applyEnrollmentStatuses(QueryBuilder $query, array $statuses): void
    {
        $studyStages = array_values(array_intersect($statuses, [
            'active',
            'intake_pre_uni_gc',
            'intake_course',
            'intake_major',
            'pending_course_opening',
        ]));
        $withdrawnStatuses = array_values(array_intersect($statuses, ['dropout', 'dropout_transfer']));
        $deferredStatuses = array_values(array_intersect($statuses, ['deferred', 'admission_deferred']));
        $enrollmentStatuses = array_values(array_diff(
            $statuses,
            $studyStages,
            $withdrawnStatuses,
            $deferredStatuses,
        ));
        $hasCondition = false;

        if ($studyStages !== []) {
            $query->where(function ($active) use ($studyStages): void {
                $active->where('lifecycle_enrollments.enrollment_status', 'active')
                    ->where(function ($stage) use ($studyStages): void {
                        if (in_array('active', $studyStages, true)) {
                            $stage->whereNull('lifecycle_enrollments.study_stage')
                                ->orWhereIn('lifecycle_enrollments.study_stage', $studyStages);

                            return;
                        }

                        $stage->whereIn('lifecycle_enrollments.study_stage', $studyStages);
                    });
            });
            $hasCondition = true;
        }

        if ($enrollmentStatuses !== []) {
            $method = $hasCondition ? 'orWhereIn' : 'whereIn';
            $query->{$method}('lifecycle_enrollments.enrollment_status', array_values(array_unique($enrollmentStatuses)));
            $hasCondition = true;
        }

        if ($withdrawnStatuses !== []) {
            $method = $hasCondition ? 'orWhere' : 'where';
            $query->{$method}(function (QueryBuilder $withdrawn) use ($withdrawnStatuses): void {
                $withdrawn->where('lifecycle_enrollments.enrollment_status', 'withdrawn')
                    ->whereRaw($this->legacyDiscriminatorSql().' in ('.implode(', ', array_fill(0, count($withdrawnStatuses), '?')).')', $withdrawnStatuses);
            });
            $hasCondition = true;
        }

        if ($deferredStatuses !== []) {
            $method = $hasCondition ? 'orWhere' : 'where';
            $query->{$method}(function (QueryBuilder $deferred) use ($deferredStatuses): void {
                $deferred->where('lifecycle_enrollments.enrollment_status', 'deferred')
                    ->whereRaw($this->legacyDiscriminatorSql().' in ('.implode(', ', array_fill(0, count($deferredStatuses), '?')).')', $deferredStatuses);
            });
        }
    }

    private function legacyDiscriminatorSql(): string
    {
        return <<<'SQL'
            COALESCE(
                (
                    SELECT lifecycle_logs.new_status
                    FROM student_action_logs AS lifecycle_logs
                    WHERE lifecycle_logs.student_id = lifecycle_enrollments.student_id
                        AND lifecycle_logs.new_status IS NOT NULL
                        AND (
                            lifecycle_enrollments.materialized_at IS NULL
                            OR lifecycle_logs.created_at >= lifecycle_enrollments.materialized_at
                        )
                    ORDER BY lifecycle_logs.id DESC
                    LIMIT 1
                ),
                JSON_UNQUOTE(JSON_EXTRACT(lifecycle_enrollments.source_snapshot, '$.status')),
                CASE
                    WHEN lifecycle_enrollments.enrollment_status = 'withdrawn' THEN 'dropout'
                    ELSE lifecycle_enrollments.enrollment_status
                END
            )
            SQL;
    }
}
