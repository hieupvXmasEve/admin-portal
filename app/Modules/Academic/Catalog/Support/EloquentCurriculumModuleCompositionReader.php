<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Support;

use App\Models\CurriculumModule;
use App\Shared\Contracts\Academic\CurriculumModuleCompositionReader;
use App\Shared\Contracts\Academic\DTO\CurriculumModuleComposition;

final class EloquentCurriculumModuleCompositionReader implements CurriculumModuleCompositionReader
{
    public function forCurriculumVersion(?int $curriculumVersionId): array
    {
        if ($curriculumVersionId === null) {
            return [];
        }

        return CurriculumModule::query()
            ->where('curriculum_version_id', $curriculumVersionId)
            ->with(['module.units' => fn ($query) => $query
                ->select('units.id', 'units.code', 'units.name', 'units.credit_points')
                ->orderBy('module_units.order')
                ->orderBy('units.id')])
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->filter(static fn (CurriculumModule $curriculumModule): bool => $curriculumModule->module !== null)
            ->map(static fn (CurriculumModule $curriculumModule): CurriculumModuleComposition => new CurriculumModuleComposition(
                moduleId: (int) $curriculumModule->module->id,
                moduleCode: (string) $curriculumModule->module->code,
                moduleName: (string) $curriculumModule->module->name,
                gradingType: $curriculumModule->module->grading_type,
                totalCredits: (float) $curriculumModule->module->total_credits,
                yearLevel: $curriculumModule->year_level,
                semesterNumber: $curriculumModule->semester_number,
                isRequired: (bool) $curriculumModule->is_required,
                groupName: $curriculumModule->group_name,
                units: $curriculumModule->module->units->map(static fn ($unit): array => [
                    'id' => (int) $unit->id,
                    'code' => (string) $unit->code,
                    'name' => (string) $unit->name,
                    'credit_points' => (float) $unit->credit_points,
                    'grading_type' => $unit->pivot->grading_type,
                    'weight' => $unit->pivot->weight === null ? null : (float) $unit->pivot->weight,
                    'order' => $unit->pivot->order === null ? null : (int) $unit->pivot->order,
                ])->all(),
            ))
            ->values()
            ->all();
    }
}
