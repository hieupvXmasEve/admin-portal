<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support\Grading;

/**
 * Immutable result returned by any GradingCalculator.
 *
 * - final_percentage: 0-100 diagnostic (null when the engine has no % equivalent, e.g. pass/fail)
 * - final_grade:      display value – "5", "3", "P", "F", "A-" …
 * - grade_points:     numeric representation used downstream (GPA, pass-check)
 * - passed:           authoritative pass outcome
 * - grade_breakdown:  auditable JSON stored in academic_records.grade_breakdown
 */
readonly class GradingResult
{
    public function __construct(
        public ?float $finalPercentage,
        public string $finalGrade,
        public float $gradePoints,
        public bool $passed,
        public array $gradeBreakdown,
    ) {}
}
