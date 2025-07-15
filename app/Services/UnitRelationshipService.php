<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;
use App\Models\EquivalentUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnitRelationshipService
{
    public function __construct(
        private UnitValidationService $validationService
    ) {}

    public function getPrerequisiteTree(Unit $unit, int $depth = 3): array
    {
        return $this->buildPrerequisiteTree($unit, $depth, []);
    }

    private function buildPrerequisiteTree(Unit $unit, int $depth, array $visited): array
    {
        if ($depth <= 0 || in_array($unit->id, $visited)) {
            return [];
        }

        $visited[] = $unit->id;
        $tree = [
            'unit' => $unit->only(['id', 'code', 'name', 'credit_points']),
            'children' => [],
        ];

        // Use the new prerequisite groups structure
        $prerequisiteGroups = $unit->prerequisiteGroups()
            ->with('conditions.requiredUnit')
            ->get();

        foreach ($prerequisiteGroups as $group) {
            foreach ($group->conditions as $condition) {
                if ($condition->requiredUnit) {
                    $childTree = $this->buildPrerequisiteTree(
                        $condition->requiredUnit,
                        $depth - 1,
                        $visited
                    );

                    if (!empty($childTree)) {
                        $tree['children'][] = array_merge($childTree, [
                            'relationship_type' => $condition->type,
                            'relationship_id' => $condition->id,
                            'group_id' => $group->id,
                            'group_operator' => $group->logic_operator,
                        ]);
                    }
                }
            }
        }

        return $tree;
    }

    public function bulkCreatePrerequisites(array $prerequisites): array
    {
        $results = [
            'successful' => [],
            'failed' => [],
            'total' => count($prerequisites),
        ];

        DB::beginTransaction();

        try {
            // Group prerequisites by unit_id to create one group per unit
            $groupedPrerequisites = [];
            foreach ($prerequisites as $prerequisite) {
                $unitId = $prerequisite['unit_id'];
                if (!isset($groupedPrerequisites[$unitId])) {
                    $groupedPrerequisites[$unitId] = [];
                }
                $groupedPrerequisites[$unitId][] = $prerequisite;
            }

            foreach ($groupedPrerequisites as $unitId => $unitPrerequisites) {
                // Create a prerequisite group for this unit
                $group = \App\Models\UnitPrerequisiteGroup::create([
                    'unit_id' => $unitId,
                    'logic_operator' => 'AND',
                    'description' => 'Bulk created prerequisites',
                ]);

                foreach ($unitPrerequisites as $prerequisite) {
                    $validation = $this->validationService->validatePrerequisiteRelationship(
                        $prerequisite['unit_id'],
                        $prerequisite['required_unit_id'],
                        $prerequisite['type'] ?? 'prerequisite'
                    );

                    if ($validation['valid']) {
                        // Create a condition in the group
                        $condition = \App\Models\UnitPrerequisiteCondition::create([
                            'group_id' => $group->id,
                            'type' => $prerequisite['type'] ?? 'prerequisite',
                            'required_unit_id' => $prerequisite['required_unit_id'],
                        ]);

                        $results['successful'][] = [
                            'id' => $condition->id,
                            'unit_code' => Unit::find($prerequisite['unit_id'])->code,
                            'required_unit_code' => Unit::find($prerequisite['required_unit_id'])->code,
                            'type' => $prerequisite['type'] ?? 'prerequisite',
                        ];
                    } else {
                        $results['failed'][] = [
                            'prerequisite' => $prerequisite,
                            'errors' => $validation['errors'],
                        ];
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    public function getEquivalencyChains(Unit $unit): Collection
    {
        // Get all units equivalent to this unit (direct and indirect)
        $equivalents = collect([$unit]);
        $processed = [$unit->id];

        $this->findEquivalentUnits($unit, $equivalents, $processed);

        return $equivalents->unique('id');
    }

    private function findEquivalentUnits(Unit $unit, Collection &$equivalents, array &$processed): void
    {
        // Find units this unit is equivalent to
        $directEquivalents = EquivalentUnit::where('unit_id', $unit->id)
            ->with('equivalentUnit')
            ->get()
            ->pluck('equivalentUnit');

        // Find units that are equivalent to this unit
        $reverseEquivalents = EquivalentUnit::where('equivalent_unit_id', $unit->id)
            ->with('unit')
            ->get()
            ->pluck('unit');

        $allEquivalents = $directEquivalents->concat($reverseEquivalents);

        foreach ($allEquivalents as $equivalent) {
            if (!in_array($equivalent->id, $processed)) {
                $equivalents->push($equivalent);
                $processed[] = $equivalent->id;

                // Recursively find equivalents
                $this->findEquivalentUnits($equivalent, $equivalents, $processed);
            }
        }
    }

    public function bulkCreateEquivalencies(array $equivalencies): array
    {
        $results = [
            'successful' => [],
            'failed' => [],
            'total' => count($equivalencies),
        ];

        DB::beginTransaction();

        try {
            foreach ($equivalencies as $equivalency) {
                $validation = $this->validationService->validateEquivalencyRelationship(
                    $equivalency['unit_id'],
                    $equivalency['equivalent_unit_id'],
                );

                if ($validation['valid']) {
                    $created = EquivalentUnit::create($equivalency);
                    $results['successful'][] = [
                        'id' => $created->id,
                        'unit_code' => Unit::find($equivalency['unit_id'])->code,
                        'equivalent_unit_code' => Unit::find($equivalency['equivalent_unit_id'])->code,
                        'reason' => $equivalency['reason'] ?? null,
                    ];
                } else {
                    $results['failed'][] = [
                        'equivalency' => $equivalency,
                        'errors' => $validation['errors'],
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $results;
    }

    public function getAllEquivalentUnits(Unit $unit): Collection
    {
        $equivalentUnits = collect();
        $processedIds = [$unit->id]; // Don't include the original unit
        $toProcess = collect([$unit]);

        while ($toProcess->isNotEmpty()) {
            $currentUnit = $toProcess->shift();

            // Get units this unit is equivalent to (outgoing relationships)
            $outgoingEquivalents = EquivalentUnit::where('unit_id', $currentUnit->id)
                ->with([
                    'equivalentUnit'
                ])
                ->get();

            // Get units that are equivalent to this unit (incoming relationships)
            $incomingEquivalents = EquivalentUnit::where('equivalent_unit_id', $currentUnit->id)
                ->with([
                    'unit'
                ])
                ->get();

            // Process outgoing relationships
            foreach ($outgoingEquivalents as $equivalent) {
                if (!in_array($equivalent->equivalent_unit_id, $processedIds)) {
                    $equivalentUnits->push([
                        'id' => $equivalent->id,
                        'unit' => $equivalent->equivalentUnit,
                        'reason' => $equivalent->reason,
                        'valid_from_semester' => null, // Field not available in current schema
                        'relationship_type' => 'equivalent_to' // This unit is equivalent to the found unit
                    ]);

                    $processedIds[] = $equivalent->equivalent_unit_id;
                    $toProcess->push($equivalent->equivalentUnit);
                }
            }

            // Process incoming relationships
            foreach ($incomingEquivalents as $equivalent) {
                if (!in_array($equivalent->unit_id, $processedIds)) {
                    $equivalentUnits->push([
                        'id' => $equivalent->id,
                        'unit' => $equivalent->unit,
                        'reason' => $equivalent->reason,
                        'valid_from_semester' => null, // Field not available in current schema
                        'relationship_type' => 'equivalent_from' // The found unit is equivalent to this unit
                    ]);

                    $processedIds[] = $equivalent->unit_id;
                    $toProcess->push($equivalent->unit);
                }
            }
        }

        // Remove duplicates based on unit ID and sort by unit code
        return $equivalentUnits->unique(function ($item) {
            return $item['unit']->id;
        })->sortBy(function ($item) {
            return $item['unit']->code;
        })->values();
    }
}
