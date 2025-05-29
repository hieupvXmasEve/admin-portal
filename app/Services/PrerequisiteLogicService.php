<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Unit;
use App\Models\UnitPrerequisiteGroup;
use App\Models\UnitPrerequisiteCondition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PrerequisiteLogicService
{
    /**
     * Parse a prerequisite expression and store it in the database.
     *
     * @param int $unitId The ID of the unit that has prerequisites
     * @param string $expression The prerequisite expression to parse
     * @param string|null $description Optional description for the prerequisite group
     * @param bool $validationOnly Whether to only validate the expression without storing it
     * @return array Result of the parsing operation with success status and message
     */
    public function parseAndStorePrerequisiteExpression(int $unitId, string $expression, ?string $description = null, bool $validationOnly = false): array
    {
        try {
            // Start a database transaction if not in validation mode
            if (!$validationOnly) {
                DB::beginTransaction();
            }

            if ($validationOnly) {
                // In validation mode, we just check if the expression can be parsed
                return $this->validateExpression($expression);
            }

            // Create the main prerequisite group
            $group = UnitPrerequisiteGroup::create([
                'unit_id' => $unitId,
                'logic_operator' => 'AND', // Default to AND for the main group
                'description' => $description,
            ]);

            // Parse the expression
            $success = $this->parseExpression($expression, $group->id);

            if (!$success) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to parse the prerequisite expression',
                ];
            }

            DB::commit();
            return [
                'success' => true,
                'message' => 'Prerequisite expression parsed and stored successfully',
                'group_id' => $group->id,
            ];
        } catch (\Exception $e) {
            if (!$validationOnly) {
                DB::rollBack();
            }
            return [
                'success' => false,
                'message' => 'Error processing prerequisite expression: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate a prerequisite expression without storing it.
     *
     * @param string $expression The expression to validate
     * @return array Result with success status and message
     */
    private function validateExpression(string $expression): array
    {
        // Check for basic structure errors
        if (empty(trim($expression))) {
            return [
                'success' => false,
                'message' => 'Expression cannot be empty',
            ];
        }

        // Check for balanced parentheses
        if (substr_count($expression, '(') !== substr_count($expression, ')')) {
            return [
                'success' => false,
                'message' => 'Unbalanced parentheses in expression',
            ];
        }

        // Check for valid logical operators
        if (preg_match('/\b(and|or)\b/i', $expression) === 0 && (
            str_contains($expression, '(') || str_contains($expression, ')')
        )) {
            return [
                'success' => false,
                'message' => 'Parentheses must contain logical expressions (and/or)',
            ];
        }

        // Check for unit codes and validate they exist
        preg_match_all('/\b[A-Z]{3}\d{5}\b/i', $expression, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $unitCode) {
                $unit = Unit::where('code', $unitCode)->first();

                if (!$unit) {
                    return [
                        'success' => false,
                        'message' => "Unit code '{$unitCode}' does not exist",
                    ];
                }
            }
        }

        // If we get here, the expression is valid
        return [
            'success' => true,
            'message' => 'Expression is valid',
        ];
    }

    /**
     * Parse a prerequisite expression and create the necessary conditions.
     *
     * @param string $expression The expression to parse
     * @param int $groupId The ID of the group to associate conditions with
     * @return bool Whether the parsing was successful
     */
    private function parseExpression(string $expression, int $groupId): bool
    {
        // Split by AND first (higher precedence)
        if (Str::contains($expression, ' and ')) {
            $parts = explode(' and ', $expression);
            foreach ($parts as $part) {
                $this->createConditionFromText(trim($part), $groupId);
            }
            return true;
        }

        // Split by OR
        if (Str::contains($expression, ' or ')) {
            $parts = explode(' or ', $expression);
            foreach ($parts as $part) {
                $this->createConditionFromText(trim($part), $groupId);
            }
            return true;
        }

        // Single condition
        return $this->createConditionFromText($expression, $groupId);
    }

    /**
     * Create a condition from a text expression.
     *
     * @param string $text The text to parse into a condition
     * @param int $groupId The ID of the group to associate the condition with
     * @return bool Whether the condition was created successfully
     */
    private function createConditionFromText(string $text, int $groupId): bool
    {
        $text = trim($text);

        // Check if this is a credit requirement
        if (preg_match('/(\d+)\s+credit\s+points?/i', $text, $matches)) {
            UnitPrerequisiteCondition::create([
                'group_id' => $groupId,
                'type' => 'credit_requirement',
                'required_credits' => (int) $matches[1],
                'free_text' => $text,
            ]);

            return true;
        }

        // Check if this is a unit code (e.g., COS10009)
        if (preg_match('/^[A-Z]{3}\d{5}$/i', $text)) {
            // Look up the unit by code
            $unit = Unit::where('code', $text)->first();

            if ($unit) {
                UnitPrerequisiteCondition::create([
                    'group_id' => $groupId,
                    'type' => 'prerequisite',
                    'required_unit_id' => $unit->id,
                ]);

                return true;
            }
        }

        // If we couldn't determine the type, store as textual
        UnitPrerequisiteCondition::create([
            'group_id' => $groupId,
            'type' => 'textual',
            'free_text' => $text,
        ]);

        return true;
    }

    /**
     * Generate a human-readable description of prerequisite requirements.
     *
     * @param int $unitId The ID of the unit to generate the description for
     * @return string The generated description
     */
    public function generatePrerequisiteDescription(int $unitId): string
    {
        $unit = Unit::findOrFail($unitId);

        // Get all prerequisite groups for this unit
        $groups = $unit->prerequisiteGroups()->with('conditions.requiredUnit')->get();

        if ($groups->isEmpty()) {
            return 'No prerequisites';
        }

        $descriptions = [];

        foreach ($groups as $group) {
            $descriptions[] = $this->generateGroupDescription($group);
        }

        return implode("\n\n", $descriptions);
    }

    /**
     * Generate a description for a prerequisite group.
     *
     * @param UnitPrerequisiteGroup $group The group to generate a description for
     * @return string The generated description
     */
    private function generateGroupDescription(UnitPrerequisiteGroup $group): string
    {
        $conditions = $group->conditions;

        if ($conditions->isEmpty()) {
            return $group->description ?? 'No conditions in this group';
        }

        $conditionDescriptions = [];

        foreach ($conditions as $condition) {
            switch ($condition->type) {
                case 'prerequisite':
                    if ($condition->requiredUnit) {
                        $conditionDescriptions[] = "{$condition->requiredUnit->code} ({$condition->requiredUnit->name})";
                    }
                    break;

                case 'co_requisite':
                    if ($condition->requiredUnit) {
                        $conditionDescriptions[] = "{$condition->requiredUnit->code} as co-requisite";
                    }
                    break;

                case 'credit_requirement':
                    $conditionDescriptions[] = "{$condition->required_credits} credit points";
                    break;

                case 'textual':
                    $conditionDescriptions[] = $condition->free_text;
                    break;

                default:
                    if ($condition->requiredUnit) {
                        $conditionDescriptions[] = "{$condition->type}: {$condition->requiredUnit->code}";
                    } elseif ($condition->free_text) {
                        $conditionDescriptions[] = "{$condition->type}: {$condition->free_text}";
                    }
            }
        }

        $operator = $group->logic_operator === 'AND' ? ' and ' : ' or ';

        return implode($operator, $conditionDescriptions);
    }

    /**
     * Check if a unit satisfies the prerequisites.
     *
     * @param int $unitId The ID of the unit to check
     * @param array $completedUnitIds Array of IDs of completed units
     * @param int $completedCredits Number of completed credit points
     * @return array Result with success status and message
     */
    public function checkPrerequisitesSatisfied(int $unitId, array $completedUnitIds, int $completedCredits): array
    {
        $unit = Unit::findOrFail($unitId);

        // Get all prerequisite groups for this unit
        $groups = $unit->prerequisiteGroups()->with('conditions')->get();

        if ($groups->isEmpty()) {
            return [
                'satisfied' => true,
                'message' => 'No prerequisites for this unit',
            ];
        }

        $unsatisfiedGroups = [];

        // Check each group
        foreach ($groups as $group) {
            $satisfied = $this->checkGroupSatisfied($group, $completedUnitIds, $completedCredits);

            if (!$satisfied) {
                $unsatisfiedGroups[] = $this->generateGroupDescription($group);
            }
        }

        if (empty($unsatisfiedGroups)) {
            return [
                'satisfied' => true,
                'message' => 'All prerequisites are satisfied',
            ];
        }

        return [
            'satisfied' => false,
            'message' => 'Missing prerequisites: ' . implode('; ', $unsatisfiedGroups),
        ];
    }

    /**
     * Check if a prerequisite group is satisfied.
     *
     * @param UnitPrerequisiteGroup $group The group to check
     * @param array $completedUnitIds Array of IDs of completed units
     * @param int $completedCredits Number of completed credit points
     * @return bool Whether the group is satisfied
     */
    private function checkGroupSatisfied(UnitPrerequisiteGroup $group, array $completedUnitIds, int $completedCredits): bool
    {
        $conditions = $group->conditions;

        if ($conditions->isEmpty()) {
            return true;
        }

        $satisfiedConditions = 0;

        foreach ($conditions as $condition) {
            $satisfied = false;

            switch ($condition->type) {
                case 'prerequisite':
                    if ($condition->required_unit_id && in_array($condition->required_unit_id, $completedUnitIds)) {
                        $satisfied = true;
                    }
                    break;

                case 'credit_requirement':
                    if ($completedCredits >= $condition->required_credits) {
                        $satisfied = true;
                    }
                    break;

                // Add more condition types as needed

                default:
                    // For now, assume other types are not satisfied
                    $satisfied = false;
            }

            if ($satisfied) {
                $satisfiedConditions++;

                // If any condition is satisfied in an OR group, the whole group is satisfied
                if ($group->logic_operator === 'OR') {
                    return true;
                }
            }
        }

        // If this is an AND group, all conditions must be satisfied
        return $group->logic_operator === 'AND' ? $satisfiedConditions === $conditions->count() : $satisfiedConditions > 0;
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
}
