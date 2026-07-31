<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\Academic\StudentLifecycleMatcher;
use Illuminate\Database\Eloquent\Builder;

final class EloquentStudentLifecycleMatcher implements StudentLifecycleMatcher
{
    public function matchingStudentIds(array $studentIds, array $statuses): array
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        $statuses = array_values(array_unique(array_filter($statuses)));

        if ($studentIds === [] || $statuses === []) {
            return [];
        }

        return ProgramEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('is_primary', true)
            ->where(function (Builder $enrollments) use ($statuses): void {
                $this->applyEnrollmentStatuses($enrollments, $statuses);
            })
            ->distinct()
            ->pluck('student_id')
            ->map(static fn (int|string $studentId): int => (int) $studentId)
            ->values()
            ->all();
    }

    /** @param list<string> $statuses */
    private function applyEnrollmentStatuses(Builder $query, array $statuses): void
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
            $query->where(function (Builder $active) use ($studyStages): void {
                $active->where('enrollment_status', 'active')
                    ->where(function (Builder $stage) use ($studyStages): void {
                        if (in_array('active', $studyStages, true)) {
                            $stage->whereNull('study_stage')
                                ->orWhereIn('study_stage', $studyStages);

                            return;
                        }

                        $stage->whereIn('study_stage', $studyStages);
                    });
            });
            $hasCondition = true;
        }

        if ($enrollmentStatuses !== []) {
            $method = $hasCondition ? 'orWhereIn' : 'whereIn';
            $query->{$method}('enrollment_status', array_values(array_unique($enrollmentStatuses)));
            $hasCondition = true;
        }

        if ($withdrawnStatuses !== []) {
            $method = $hasCondition ? 'orWhere' : 'where';
            $query->{$method}(function (Builder $withdrawn) use ($withdrawnStatuses): void {
                $withdrawn->where('enrollment_status', 'withdrawn')
                    ->whereRaw($this->legacyDiscriminatorSql().' in ('.implode(', ', array_fill(0, count($withdrawnStatuses), '?')).')', $withdrawnStatuses);
            });
            $hasCondition = true;
        }

        if ($deferredStatuses !== []) {
            $method = $hasCondition ? 'orWhere' : 'where';
            $query->{$method}(function (Builder $deferred) use ($deferredStatuses): void {
                $deferred->where('enrollment_status', 'deferred')
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
                    WHERE lifecycle_logs.student_id = program_enrollments.student_id
                        AND lifecycle_logs.new_status IS NOT NULL
                        AND (
                            program_enrollments.materialized_at IS NULL
                            OR lifecycle_logs.created_at >= program_enrollments.materialized_at
                        )
                    ORDER BY lifecycle_logs.id DESC
                    LIMIT 1
                ),
                JSON_UNQUOTE(JSON_EXTRACT(program_enrollments.source_snapshot, '$.status')),
                CASE
                    WHEN program_enrollments.enrollment_status = 'withdrawn' THEN 'dropout'
                    ELSE program_enrollments.enrollment_status
                END
            )
            SQL;
    }
}
