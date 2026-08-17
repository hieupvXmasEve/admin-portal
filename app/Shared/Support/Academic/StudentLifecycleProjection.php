<?php

declare(strict_types=1);

namespace App\Shared\Support\Academic;

use Illuminate\Database\Eloquent\Builder;

/**
 * SQL twin of `App\Modules\Academic\Progression\Support\StudentLifecycleStatusReader::legacyStatus()`.
 *
 * `students.status` is write-dead: only set at student creation, never synced
 * back on a program-enrollment transition. The canonical lifecycle status is
 * the student's primary `program_enrollments` row, falling back to the frozen
 * column only for students who were never materialized into an enrollment.
 *
 * Primary-row tie-break (binding, plan `260817-0017`): a student may legally
 * hold more than one `is_primary = 1` row (the unique index only constrains
 * primary+active), so **highest `id` wins** — matching the PHP reader's
 * `orderBy('id')` + `mapWithKeys` overwrite semantics.
 *
 * Placed under `App\Shared\` (not a module) because it is consumed across
 * StudentRegistry, Finance and Academic — a cross-module import from any one
 * of those would fail `DomainBoundaryArchitectureTest`.
 */
final class StudentLifecycleProjection
{
    /**
     * Scalar subquery resolving the id of the primary enrollment row that
     * wins the tie-break (highest id among `is_primary = 1` rows).
     */
    public static function primaryEnrollmentIdSubquery(string $studentsTable = 'students'): string
    {
        return "(SELECT MAX(spe.id) FROM program_enrollments spe WHERE spe.student_id = {$studentsTable}.id AND spe.is_primary = 1)";
    }

    /**
     * The projected lifecycle status as a single SQL expression: current
     * `enrollment_status`/`study_stage` from the tie-break-winning primary
     * enrollment, falling back to `students.status` when no enrollment was
     * ever materialized.
     */
    public static function caseExpression(string $studentsTable = 'students'): string
    {
        $primaryId = self::primaryEnrollmentIdSubquery($studentsTable);

        // Literal template (not built from a separately-interpolated status
        // subquery) so the CASE...enrollment_status pattern stays textually
        // whole here — the one place phase 5's guard expects to find it.
        $template = 'CASE WHEN (SELECT pe.enrollment_status FROM program_enrollments pe WHERE pe.id = __PRIMARY_ID__) IS NULL THEN __TABLE__.status '
            ."WHEN (SELECT pe.enrollment_status FROM program_enrollments pe WHERE pe.id = __PRIMARY_ID__) = 'active' THEN COALESCE((SELECT pe.study_stage FROM program_enrollments pe WHERE pe.id = __PRIMARY_ID__), 'active') "
            ."WHEN (SELECT pe.enrollment_status FROM program_enrollments pe WHERE pe.id = __PRIMARY_ID__) = 'withdrawn' THEN 'dropout' "
            .'ELSE (SELECT pe.enrollment_status FROM program_enrollments pe WHERE pe.id = __PRIMARY_ID__) END';

        return str_replace(['__PRIMARY_ID__', '__TABLE__'], [$primaryId, $studentsTable], $template);
    }

    /**
     * Filter a query builder to rows whose projected lifecycle status is in
     * (or, with `$not`, not in) the given list.
     *
     * @param  Builder<*>  $query
     * @param  list<string>  $statuses
     * @return Builder<*>
     */
    public static function whereStatusIn(Builder $query, array $statuses, bool $not = false, string $studentsTable = 'students'): Builder
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $operator = $not ? 'NOT IN' : 'IN';

        return $query->whereRaw(
            self::caseExpression($studentsTable)." {$operator} ({$placeholders})",
            $statuses,
        );
    }
}
