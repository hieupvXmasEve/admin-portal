<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicRecord;
use App\Models\Module;
use Illuminate\Support\Collection;

/**
 * Single source of truth for a module's aggregated grade.
 *
 * Shared by ModuleProgressService and GradeService so the two surfaces can never
 * drift apart again.
 */
class ModuleGradeCalculator
{
    /**
     * Credit-weighted average of the 0-5 sub-unit grades for a module.
     *
     * - Sub-units are classified graded vs pass/fail by the `module_units` pivot
     *   `grading_type` (NOT the course offering).
     * - The grade is withheld (null) until every graded sub-unit is resolved and
     *   passed, and when the module has no graded sub-units.
     * - The result is rounded once to a whole 0-5 grade.
     *
     * @param  Collection<int, AcademicRecord>  $records  academic records for the module's units
     */
    public function calculate(Collection $records, Module $module): ?float
    {
        $subUnits = $module->relationLoaded('units') ? $module->units : $module->units()->get();

        $gradedRecords = $records->filter(function (AcademicRecord $record) use ($subUnits): bool {
            $unit = $subUnits->firstWhere('id', $record->unit_id);

            return $unit !== null && $unit->pivot->grading_type === 'grade';
        });

        if ($gradedRecords->isEmpty()) {
            return null;
        }

        // Withhold the module grade until every graded sub-unit is resolved and passed.
        $allResolvedAndPassed = $gradedRecords->every(
            fn (AcademicRecord $record): bool => in_array($record->completion_status, ['completed', 'failed'], true)
                && $record->isPassed()
        );

        if (! $allResolvedAndPassed) {
            return null;
        }

        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($gradedRecords as $record) {
            $grade = $this->gradeValue($record);

            if ($grade === null) {
                continue;
            }

            $unit = $subUnits->firstWhere('id', $record->unit_id);
            // Explicit pivot weight wins; otherwise weight by the unit's credit points.
            $weight = (float) ($unit?->pivot->weight ?? $unit?->credit_points ?? 1);

            $weightedSum += $grade * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return null;
        }

        // Round once after combining, to a whole 0-5 grade.
        return (float) round($weightedSum / $totalWeight);
    }

    /**
     * Authoritative 0-5 grade for a record.
     *
     * The aggregate-manual finalization path writes the engine grade into
     * grade_breakdown['grade_points'] but does not refresh the top-level
     * grade_points column, so the breakdown is the source of truth.
     */
    private function gradeValue(AcademicRecord $record): ?float
    {
        $breakdown = is_array($record->grade_breakdown) ? $record->grade_breakdown : [];

        if (array_key_exists('grade_points', $breakdown) && $breakdown['grade_points'] !== null) {
            return (float) $breakdown['grade_points'];
        }

        return $record->grade_points !== null ? (float) $record->grade_points : null;
    }
}
