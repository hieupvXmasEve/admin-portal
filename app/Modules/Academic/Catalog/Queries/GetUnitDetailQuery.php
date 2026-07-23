<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\EquivalentUnit;
use App\Models\Unit;
use App\Services\PrerequisiteLogicService;
use Illuminate\Support\Collection;

class GetUnitDetailQuery
{
    public function __construct(private readonly PrerequisiteLogicService $prerequisiteLogic) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Unit $unit): array
    {
        $unit->load([
            'curriculumUnits.curriculumVersion.program',
            'curriculumUnits.curriculumVersion.specialization',
            'curriculumUnits.semester',
            'prerequisiteGroups.conditions.requiredUnit',
            'syllabusTemplates.assessmentComponents',
        ]);

        $equivalentUnits = $this->equivalentUnits($unit);
        $prerequisiteGroups = $unit->prerequisiteGroups->map(fn ($group): array => [
            'id' => $group->id,
            'logic_operator' => $group->logic_operator,
            'description' => $group->description,
            'conditions' => $group->conditions->map(fn ($condition): array => [
                'id' => $condition->id,
                'type' => $condition->type,
                'required_unit_id' => $condition->required_unit_id,
                'unit' => $condition->requiredUnit === null ? null : [
                    'id' => $condition->requiredUnit->id,
                    'code' => $condition->requiredUnit->code,
                    'name' => $condition->requiredUnit->name,
                    'credit_points' => (float) $condition->requiredUnit->credit_points,
                ],
                'required_credits' => $condition->required_credits,
                'free_text' => $condition->free_text,
            ])->all(),
        ]);
        $unit->setAttribute('prerequisite_groups', $prerequisiteGroups);

        return [
            'unit' => $unit,
            'equivalentUnits' => $equivalentUnits,
            'prerequisiteDescriptions' => $unit->prerequisiteGroups->isNotEmpty()
                ? $this->prerequisiteLogic->generatePrerequisiteDescription($unit->id)
                : null,
            'relationshipStats' => [
                'prerequisite_count' => $unit->prerequisiteConditions()->where('type', 'prerequisite')->count(),
                'corequisite_count' => $unit->prerequisiteConditions()->where('type', 'co_requisite')->count(),
                'antirequisite_count' => $unit->prerequisiteConditions()->where('type', 'anti_requisite')->count(),
                'prerequisite_conditions_count' => $unit->prerequisiteConditions()->count(),
                'equivalent_count' => $equivalentUnits->count(),
                'curriculum_count' => $unit->curriculumUnits()->count(),
                'syllabus_templates_count' => $unit->syllabusTemplates()->count(),
                'active_syllabus_templates_count' => $unit->syllabusTemplates()->where('is_active', true)->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function equivalentUnits(Unit $unit): Collection
    {
        $equivalentUnits = collect();
        $processedIds = [$unit->id];
        $pending = collect([$unit]);

        while ($pending->isNotEmpty()) {
            /** @var Unit $current */
            $current = $pending->shift();
            $outgoing = EquivalentUnit::query()->where('unit_id', $current->id)->with('equivalentUnit')->get();
            $incoming = EquivalentUnit::query()->where('equivalent_unit_id', $current->id)->with('unit')->get();

            foreach ($outgoing as $relationship) {
                if (in_array($relationship->equivalent_unit_id, $processedIds, true)) {
                    continue;
                }
                $equivalentUnits->push([
                    'id' => $relationship->id,
                    'unit' => $relationship->equivalentUnit,
                    'reason' => $relationship->reason,
                    'valid_from_semester' => null,
                    'relationship_type' => 'equivalent_to',
                ]);
                $processedIds[] = $relationship->equivalent_unit_id;
                $pending->push($relationship->equivalentUnit);
            }

            foreach ($incoming as $relationship) {
                if (in_array($relationship->unit_id, $processedIds, true)) {
                    continue;
                }
                $equivalentUnits->push([
                    'id' => $relationship->id,
                    'unit' => $relationship->unit,
                    'reason' => $relationship->reason,
                    'valid_from_semester' => null,
                    'relationship_type' => 'equivalent_from',
                ]);
                $processedIds[] = $relationship->unit_id;
                $pending->push($relationship->unit);
            }
        }

        return $equivalentUnits;
    }
}
