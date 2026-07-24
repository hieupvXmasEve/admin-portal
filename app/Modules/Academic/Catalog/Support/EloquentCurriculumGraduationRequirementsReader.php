<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Support;

use App\Models\CurriculumUnit;
use App\Shared\Contracts\Academic\CurriculumGraduationRequirementsReader;
use App\Shared\Contracts\Academic\DTO\CurriculumGraduationRequirement;

final class EloquentCurriculumGraduationRequirementsReader implements CurriculumGraduationRequirementsReader
{
    public function forCurriculumVersion(?int $curriculumVersionId): array
    {
        if ($curriculumVersionId === null) {
            return [];
        }

        return CurriculumUnit::query()
            ->with('unit:id,code,credit_points')
            ->where('curriculum_version_id', $curriculumVersionId)
            ->orderBy('id')
            ->get()
            ->filter(static fn (CurriculumUnit $curriculumUnit): bool => $curriculumUnit->unit !== null)
            ->map(static fn (CurriculumUnit $curriculumUnit): CurriculumGraduationRequirement => new CurriculumGraduationRequirement(
                unitId: (int) $curriculumUnit->unit_id,
                unitCode: (string) $curriculumUnit->unit->code,
                creditPoints: (float) $curriculumUnit->unit->credit_points,
                type: (string) $curriculumUnit->type,
            ))
            ->all();
    }
}
