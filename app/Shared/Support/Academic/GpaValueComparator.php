<?php

declare(strict_types=1);

namespace App\Shared\Support\Academic;

/**
 * The single definition of "these GPA values are the same".
 *
 * Policy, not record access: it normalizes and compares already-computed GPA
 * values and never reads a model. Extracting the rule here (from the
 * reconciliation query's private normalize()) is what makes the finalize guard,
 * the finalize-page divergence badge, and the GPA-001 audit agree by
 * construction instead of by two similar implementations drifting apart. Kept
 * beside CourseGradeScale as a stateless Academic policy helper: no interface,
 * no provider binding.
 */
final class GpaValueComparator
{
    /**
     * The persisted GPA fields whose recomputed values decide divergence — the
     * same set the reconciliation audit (GPA-001) compares.
     *
     * @var list<string>
     */
    public const COMPARISON_FIELDS = [
        'semester_gpa', 'cumulative_gpa', 'semester_quality_points', 'cumulative_quality_points',
        'semester_credit_points', 'cumulative_credit_points', 'semester_credit_points_earned',
        'cumulative_credit_points_earned', 'academic_standing', 'program_id',
    ];

    /**
     * The canonical persisted-GPA row assembled from freshly computed values —
     * the shape the finalize guard writes and the finalize page compares against
     * the stored snapshot. One builder so those two callers cannot drift, which
     * is what makes the page's divergence badge a truthful prediction of what
     * clicking Finalize would write.
     *
     * @param  array{gpa: float|int, quality_points: float|int, credit_points: float|int, credit_points_earned: float|int}  $semesterData
     * @param  array{gpa: float|int, quality_points: float|int, credit_points: float|int, credit_points_earned: float|int}  $cumulativeData
     * @return array<string, mixed>
     */
    public static function recomputedRow(array $semesterData, array $cumulativeData, string $standing, int|string|null $programId): array
    {
        return [
            'program_id' => $programId,
            'semester_gpa' => $semesterData['gpa'],
            'cumulative_gpa' => $cumulativeData['gpa'],
            'semester_quality_points' => $semesterData['quality_points'],
            'cumulative_quality_points' => $cumulativeData['quality_points'],
            // Credit fields are stored as decimal:2, so the DB truncates on write.
            // Round the recomputed side to the same precision so the compare is
            // exact — otherwise a 3rd-decimal recomputed value would never equal
            // the stored 2-dp value and the row would read as perpetually
            // divergent. (No data change: the cast truncates to 2 dp regardless.)
            'semester_credit_points' => round((float) $semesterData['credit_points'], 2),
            'cumulative_credit_points' => round((float) $cumulativeData['credit_points'], 2),
            'semester_credit_points_earned' => round((float) $semesterData['credit_points_earned'], 2),
            'cumulative_credit_points_earned' => round((float) $cumulativeData['credit_points_earned'], 2),
            'academic_standing' => $standing,
        ];
    }

    /**
     * Canonical comparison string for one value. NOT an epsilon tolerance:
     * 3-dp numeric normalization matching the decimal:3 casts and round(..., 3)
     * used across the GPA pipeline, with bool -> 'true'/'false', null -> 'null'.
     */
    public static function normalize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return number_format((float) $value, 3, '.', '');
        }

        return $value === null ? 'null' : (string) $value;
    }

    /**
     * True when every compared field normalizes equal between two value-sets.
     *
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $recomputed
     * @param  list<string>|null  $fields  defaults to COMPARISON_FIELDS
     */
    public static function matches(array $stored, array $recomputed, ?array $fields = null): bool
    {
        foreach ($fields ?? self::COMPARISON_FIELDS as $field) {
            if (self::normalize($stored[$field] ?? null) !== self::normalize($recomputed[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
