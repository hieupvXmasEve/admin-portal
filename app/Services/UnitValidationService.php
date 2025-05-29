<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Collection;

class UnitValidationService
{
    public function canDeleteUnit(Unit $unit): array
    {
        $restrictions = [];

        // Check for active curriculum relationships
        if ($unit->curriculumUnits()->exists()) {
            $restrictions[] = 'Unit is part of active curricula';
        }

        // Check for prerequisite relationships using the new system
        if ($unit->prerequisiteGroups()->exists()) {
            $restrictions[] = 'Unit has prerequisite groups';
        }

        // Check if this unit is required by other units (through prerequisite conditions)
        $isRequiredByOthers = \App\Models\UnitPrerequisiteCondition::where('required_unit_id', $unit->id)->exists();
        if ($isRequiredByOthers) {
            $restrictions[] = 'Unit is required by other units';
        }

        // Check for equivalency relationships
        if ($unit->equivalentUnits()->exists() || $unit->equivalentTo()->exists()) {
            $restrictions[] = 'Unit has equivalency relationships';
        }

        return [
            'allowed' => empty($restrictions),
            'reason' => empty($restrictions) ? null : implode(', ', $restrictions),
            'restrictions' => $restrictions,
        ];
    }

    public function canEditUnit(Unit $unit): bool
    {
        // Units with extensive relationships may have edit restrictions
        // For now, allow editing unless there are active curriculum relationships
        $criticalRelationships = $unit->curriculumUnits()->count();

        // Allow editing if there are no curriculum relationships
        return $criticalRelationships === 0;
    }

    public function getEditRestrictions(Unit $unit): array
    {
        $restrictions = [];

        if (!$this->canEditUnit($unit)) {
            $restrictions[] = [
                'field' => 'code',
                'reason' => 'Cannot change code for units in active curricula',
            ];
            $restrictions[] = [
                'field' => 'credit_points',
                'reason' => 'Cannot change credit points for units in active curricula',
            ];
        }

        return $restrictions;
    }

    public function validatePrerequisiteRelationship(int $unitId, int $requiredUnitId, string $type): array
    {
        // Self-reference check
        if ($unitId === $requiredUnitId) {
            return [
                'valid' => false,
                'errors' => ['Unit cannot be a prerequisite to itself'],
            ];
        }

        // Check if this relationship already exists in any prerequisite group
        $exists = \App\Models\UnitPrerequisiteCondition::whereHas('group', function ($query) use ($unitId) {
            $query->where('unit_id', $unitId);
        })->where('required_unit_id', $requiredUnitId)->exists();

        if ($exists) {
            return [
                'valid' => false,
                'errors' => ['This prerequisite relationship already exists'],
            ];
        }

        // Circular dependency check
        $circularCheck = $this->checkCircularDependency($unitId, $requiredUnitId);
        if (!$circularCheck['valid']) {
            return $circularCheck;
        }

        // Type-specific validations
        $typeValidation = $this->validatePrerequisiteType($unitId, $requiredUnitId, $type);
        if (!$typeValidation['valid']) {
            return $typeValidation;
        }

        return ['valid' => true, 'errors' => []];
    }

    private function checkCircularDependency(int $unitId, int $requiredUnitId): array
    {
        // Use depth-first search to detect cycles
        $visited = [];
        $recursionStack = [];

        $hasCycle = $this->dfsCircularCheck($requiredUnitId, $unitId, $visited, $recursionStack);

        return [
            'valid' => !$hasCycle,
            'errors' => $hasCycle ? ['This would create a circular dependency'] : [],
        ];
    }

    private function dfsCircularCheck(int $currentUnit, int $targetUnit, array &$visited, array &$recursionStack): bool
    {
        $visited[$currentUnit] = true;
        $recursionStack[$currentUnit] = true;

        // Get all prerequisites of current unit using the new system
        $prerequisites = \App\Models\UnitPrerequisiteCondition::whereHas('group', function ($query) use ($currentUnit) {
            $query->where('unit_id', $currentUnit);
        })->where('required_unit_id', '!=', null)->pluck('required_unit_id');

        foreach ($prerequisites as $prerequisite) {
            if ($prerequisite == $targetUnit) {
                return true; // Found cycle
            }

            if (
                !isset($visited[$prerequisite]) &&
                $this->dfsCircularCheck($prerequisite, $targetUnit, $visited, $recursionStack)
            ) {
                return true;
            }

            if (isset($recursionStack[$prerequisite]) && $recursionStack[$prerequisite]) {
                return true;
            }
        }

        $recursionStack[$currentUnit] = false;
        return false;
    }

    private function validatePrerequisiteType(int $unitId, int $requiredUnitId, string $type): array
    {
        switch ($type) {
            case 'antirequisite':
                // Check if there's already a prerequisite or corequisite relationship
                $conflictingRelation = \App\Models\UnitPrerequisiteCondition::whereHas('group', function ($query) use ($unitId) {
                    $query->where('unit_id', $unitId);
                })->where('required_unit_id', $requiredUnitId)
                    ->whereIn('type', ['prerequisite', 'corequisite'])
                    ->exists();

                if ($conflictingRelation) {
                    return [
                        'valid' => false,
                        'errors' => ['Cannot create antirequisite with existing prerequisite/corequisite'],
                    ];
                }
                break;

            case 'prerequisite':
            case 'corequisite':
                // Check if there's already an antirequisite relationship
                $conflictingRelation = \App\Models\UnitPrerequisiteCondition::whereHas('group', function ($query) use ($unitId) {
                    $query->where('unit_id', $unitId);
                })->where('required_unit_id', $requiredUnitId)
                    ->where('type', 'antirequisite')
                    ->exists();

                if ($conflictingRelation) {
                    return [
                        'valid' => false,
                        'errors' => ['Cannot create prerequisite/corequisite with existing antirequisite'],
                    ];
                }
                break;
        }

        return ['valid' => true, 'errors' => []];
    }

    public function validateEquivalencyRelationship(int $unitId, int $equivalentUnitId, int $semesterId): array
    {
        // Self-reference check
        if ($unitId === $equivalentUnitId) {
            return [
                'valid' => false,
                'errors' => ['Unit cannot be equivalent to itself'],
            ];
        }

        // Duplicate relationship check
        $exists = \App\Models\EquivalentUnit::where('unit_id', $unitId)
            ->where('equivalent_unit_id', $equivalentUnitId)
            ->exists();

        if ($exists) {
            return [
                'valid' => false,
                'errors' => ['This equivalency relationship already exists'],
            ];
        }

        // Check for reverse relationship
        $reverseExists = \App\Models\EquivalentUnit::where('unit_id', $equivalentUnitId)
            ->where('equivalent_unit_id', $unitId)
            ->exists();

        if ($reverseExists) {
            return [
                'valid' => false,
                'errors' => ['Reverse equivalency relationship already exists'],
            ];
        }

        // Basic semester validity check
        $semester = \App\Models\Semester::find($semesterId);
        if (!$semester) {
            return [
                'valid' => false,
                'errors' => ['Invalid semester selected'],
            ];
        }

        return ['valid' => true, 'errors' => []];
    }
}
