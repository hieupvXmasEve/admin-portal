<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitService
{
    public function __construct(
        private PrerequisiteLogicService $prerequisiteLogicService
    ) {}

    /**
     * Create a new unit with its relationships.
     *
     * @param  array  $data  Validated data from the request.
     */
    public function createUnit(array $data): Unit
    {
        return DB::transaction(function () use ($data) {
            Log::info('Creating a new unit', ['code' => $data['code']]);

            $unit = Unit::create($data);

            if (isset($data['prerequisite_groups'])) {
                $this->syncPrerequisiteGroups($unit, $data['prerequisite_groups']);
            } elseif (isset($data['prerequisite_expression'])) {
                $this->parseAndStoreExpression($unit, $data['prerequisite_expression'], $data['prerequisite_description'] ?? null);
            }

            if (isset($data['equivalent_units'])) {
                $this->syncEquivalentUnits($unit, $data['equivalent_units']);
            }

            return $unit;
        });
    }

    /**
     * Update an existing unit and its relationships.
     *
     * @param  Unit  $unit  The unit to update.
     * @param  array  $data  Validated data from the request.
     */
    public function updateUnit(Unit $unit, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $data) {
            Log::info("Updating unit {$unit->id}", ['code' => $data['code']]);

            $unit->update($data);

            if (isset($data['prerequisite_groups'])) {
                $this->syncPrerequisiteGroups($unit, $data['prerequisite_groups']);
            } elseif (isset($data['prerequisite_expression'])) {
                $this->parseAndStoreExpression($unit, $data['prerequisite_expression'], $data['prerequisite_description'] ?? null);
            }

            if (isset($data['equivalent_units'])) {
                $this->syncEquivalentUnits($unit, $data['equivalent_units']);
            }

            return $unit;
        });
    }

    /**
     * Delete a unit.
     *
     * @param  Unit  $unit  The unit to delete.
     */
    public function deleteUnit(Unit $unit): void
    {
        DB::transaction(function () use ($unit) {
            Log::warning("Deleting unit {$unit->id}");
            
            // Delete prerequisite groups and their conditions
            $unit->prerequisiteGroups()->each(function ($group) {
                $group->conditions()->delete();
                $group->delete();
            });
            
            // Delete equivalent unit relationships
            $unit->equivalentUnits()->delete();
            
            // Finally delete the unit itself
            $unit->delete();
            
            Log::info("Unit {$unit->id} deleted successfully");
        });
    }

    private function syncPrerequisiteGroups(Unit $unit, array $groupsData): void
    {
        $unit->prerequisiteGroups()->delete();
        foreach ($groupsData as $groupData) {
            $group = $unit->prerequisiteGroups()->create([
                'logic_operator' => $groupData['logic_operator'] ?? 'AND',
                'description' => $groupData['description'] ?? null,
            ]);

            if (isset($groupData['conditions'])) {
                foreach ($groupData['conditions'] as $conditionData) {
                    $group->conditions()->create($conditionData);
                }
            }
        }
    }

    private function parseAndStoreExpression(Unit $unit, string $expression, ?string $description): void
    {
        $unit->prerequisiteGroups()->delete();
        $result = $this->prerequisiteLogicService->parseAndStorePrerequisiteExpression(
            $unit->id,
            $expression,
            $description
        );

        if (! $result['success']) {
            throw new \Exception($result['message']);
        }
    }

    private function syncEquivalentUnits(Unit $unit, array $equivalentsData): void
    {
        $unit->equivalentUnits()->delete();
        foreach ($equivalentsData as $equivalentData) {
            $unit->equivalentUnits()->create($equivalentData);
        }
    }
}
