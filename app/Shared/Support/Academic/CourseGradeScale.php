<?php

declare(strict_types=1);

namespace App\Shared\Support\Academic;

/**
 * The institution's percentage-to-grade scale and its failure vocabulary.
 *
 * These are policy, not record access: a calculator that turns a weighted
 * percentage into a letter grade, or a classifier that names why a student
 * failed, needs the scale but has no business reading academic records. Keeping
 * both here lets those callers stay off the AcademicRecord model, and gives the
 * scale one definition instead of one per consumer.
 *
 * `AcademicRecord` delegates its own static helpers and failure constants here,
 * so persisted values can never drift from this scale.
 */
final class CourseGradeScale
{
    public const FAILURE_GRADE_FAILED = 'grade_failed';

    public const FAILURE_ATTENDANCE_FAILED = 'attendance_failed';

    public const FAILURE_BOTH_FAILED = 'both_failed';

    public const FAILURE_MANUAL_FAILED = 'manual_failed';

    /** @var list<string> */
    public const FAILURE_REASONS = [
        self::FAILURE_GRADE_FAILED,
        self::FAILURE_ATTENDANCE_FAILED,
        self::FAILURE_BOTH_FAILED,
        self::FAILURE_MANUAL_FAILED,
    ];

    /**
     * Convert a percentage to the 4.0-scale grade points, clamped to [0, 4].
     */
    public static function gradePoints(float $percentage): float
    {
        $gpa = ($percentage * 4) / 100;

        return round(max(0, min(4.0, $gpa)), 2);
    }

    /**
     * Convert a percentage to its letter grade.
     */
    public static function letterGrade(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'A+',
            $percentage >= 85 => 'A',
            $percentage >= 80 => 'A-',
            $percentage >= 75 => 'B+',
            $percentage >= 70 => 'B',
            $percentage >= 65 => 'B-',
            $percentage >= 60 => 'C+',
            $percentage >= 55 => 'C',
            $percentage >= 50 => 'C-',
            default => 'F',
        };
    }
}
