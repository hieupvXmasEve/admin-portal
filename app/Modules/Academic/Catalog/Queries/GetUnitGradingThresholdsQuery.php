<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Unit;

/**
 * Unit identity plus its active syllabus template's grading thresholds, so
 * non-Catalog Academic reporting pages reach Unit through a Catalog-owned
 * seam instead of importing the shared model directly.
 */
class GetUnitGradingThresholdsQuery
{
    /**
     * Threshold values are returned exactly as the syllabus template's
     * `decimal:2` cast yields (string) or the literal float default, to
     * preserve the original display formatting.
     *
     * @return array{id: int, code: string, name: string, min_attendance_threshold: string|float, min_grade_threshold: string|float}
     */
    public function handle(int $unitId): array
    {
        $unit = Unit::findOrFail($unitId);

        $activeSyllabus = $unit->activeSyllabusTemplates()->where('is_default', true)->first()
            ?? $unit->activeSyllabusTemplates()->orderBy('version', 'desc')->first();

        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'min_attendance_threshold' => $activeSyllabus?->min_attendance_threshold ?? 80.00,
            'min_grade_threshold' => $activeSyllabus?->min_grade_threshold ?? 60.00,
        ];
    }
}
