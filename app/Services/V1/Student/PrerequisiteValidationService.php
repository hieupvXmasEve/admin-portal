<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\Unit;
use App\Models\UnitPrerequisiteCondition;
use App\Models\UnitPrerequisiteGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PrerequisiteValidationService
{
    /**
     * Check if student has met all prerequisites for a course offering
     */
    public function hasMetPrerequisites(Student $student, CourseOffering $courseOffering): bool
    {
        $unit = $courseOffering->unit;
        $prerequisiteGroups = $this->getPrerequisiteGroups($unit);

        if ($prerequisiteGroups->isEmpty()) {
            return true;
        }

        return $this->validatePrerequisiteGroups($student, $prerequisiteGroups);
    }

    /**
     * Get detailed prerequisite validation results
     */
    public function getPrerequisiteValidation(Student $student, CourseOffering $courseOffering): array
    {
        $unit = $courseOffering->unit;
        $prerequisiteGroups = $this->getPrerequisiteGroups($unit);

        if ($prerequisiteGroups->isEmpty()) {
            return [
                'has_prerequisites' => false,
                'all_met' => true,
                'groups' => [],
                'missing_groups' => [],
            ];
        }

        $validationResults = [];
        $missingGroups = [];

        foreach ($prerequisiteGroups as $group) {
            $result = $this->validateSingleGroup($student, $group);
            $validationResults[] = $result;

            if (! $result['met']) {
                $missingGroups[] = $result;
            }
        }

        return [
            'has_prerequisites' => true,
            'all_met' => empty($missingGroups),
            'groups' => $validationResults,
            'missing_groups' => $missingGroups,
        ];
    }

    /**
     * Get prerequisite groups for a unit
     */
    protected function getPrerequisiteGroups(Unit $unit): Collection
    {
        $cacheKey = "unit_prerequisite_groups:{$unit->id}";

        return Cache::remember($cacheKey, 3600, function () use ($unit) {
            return UnitPrerequisiteGroup::where('unit_id', $unit->id)
                ->with(['conditions.requiredUnit'])
                ->orderBy('id')
                ->get();
        });
    }

    /**
     * Validate all prerequisite groups for a student
     */
    protected function validatePrerequisiteGroups(Student $student, Collection $prerequisiteGroups): bool
    {
        foreach ($prerequisiteGroups as $group) {
            $result = $this->validateSingleGroup($student, $group);
            if (! $result['met']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate a single prerequisite group
     */
    protected function validateSingleGroup(Student $student, UnitPrerequisiteGroup $group): array
    {
        $conditions = $group->conditions;
        $metConditions = 0;
        $conditionResults = [];

        foreach ($conditions as $condition) {
            $result = $this->validateSingleCondition($student, $condition);
            $conditionResults[] = $result;

            if ($result['met']) {
                $metConditions++;

                // For OR groups, if any condition is met, the group is satisfied
                if ($group->logic_operator === 'OR') {
                    break;
                }
            }
        }

        // Determine if group is satisfied based on logic operator
        $groupMet = match ($group->logic_operator) {
            'AND' => $metConditions === $conditions->count(),
            'OR' => $metConditions >= 1,
            default => $metConditions === $conditions->count(), // Default to AND logic
        };

        return [
            'group_id' => $group->id,
            'logic_operator' => $group->logic_operator,
            'description' => $group->description,
            'conditions' => $conditionResults,
            'met_conditions' => $metConditions,
            'total_conditions' => $conditions->count(),
            'met' => $groupMet,
        ];
    }

    /**
     * Validate a single prerequisite condition
     */
    protected function validateSingleCondition(Student $student, UnitPrerequisiteCondition $condition): array
    {
        $met = false;
        $gradeAchieved = null;
        $completionDate = null;
        $unit = null;

        if ($condition->type === 'prerequisite' && $condition->requiredUnit) {
            $unit = $condition->requiredUnit;

            // Find any record where student passed: is_passed=true or override_pass=true
            $academicRecord = $student->academicRecords()
                ->where('unit_id', $unit->id)
                ->where(function ($q) {
                    $q->where('is_passed', true)
                        ->orWhere('override_pass', true);
                })
                ->orderBy('completion_date', 'desc')
                ->first();

            if ($academicRecord) {
                $gradeAchieved = $academicRecord->final_letter_grade;
                $completionDate = $academicRecord->completion_date;
                $met = true;
            }
        } elseif ($condition->type === 'credit_requirement') {
            // Check if student has required credit points
            $totalCredits = $student->academicRecords()
                ->where('completion_status', 'completed')
                ->sum('credit_points');

            $met = $totalCredits >= $condition->required_credits;
        } elseif ($condition->type === 'textual') {
            // Textual conditions need manual review - assume not met
            $met = false;
        }

        return [
            'condition_id' => $condition->id,
            'type' => $condition->type,
            'unit' => $unit ? [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
            ] : null,
            'required_credits' => $condition->required_credits,
            'free_text' => $condition->free_text,
            'grade_achieved' => $gradeAchieved,
            'completion_date' => $completionDate?->toDateString(),
            'met' => $met,
        ];
    }

    /**
     * Check if achieved grade meets minimum requirement
     */
    protected function gradeMetsMinimum(?string $achievedGrade, ?string $minimumGrade): bool
    {
        if (! $achievedGrade || ! $minimumGrade) {
            return false;
        }

        $gradeValues = [
            'HD' => 4,
            'D' => 3,
            'C' => 2,
            'P' => 1,
            'N' => 0,
            'F' => 0,
        ];

        $achievedValue = $gradeValues[$achievedGrade] ?? 0;
        $minimumValue = $gradeValues[$minimumGrade] ?? 0;

        return $achievedValue >= $minimumValue;
    }

    /**
     * Get prerequisite tree for a unit (recursive)
     */
    public function getPrerequisiteTree(Unit $unit, int $depth = 0, int $maxDepth = 5): array
    {
        if ($depth > $maxDepth) {
            return [];
        }

        $prerequisiteGroups = $this->getPrerequisiteGroups($unit);

        if ($prerequisiteGroups->isEmpty()) {
            return [];
        }

        $tree = [];

        foreach ($prerequisiteGroups as $group) {
            $groupNode = [
                'group_id' => $group->id,
                'logic_operator' => $group->logic_operator,
                'description' => $group->description,
                'depth' => $depth,
                'conditions' => [],
            ];

            foreach ($group->conditions as $condition) {
                if ($condition->type === 'prerequisite' && $condition->requiredUnit) {
                    $prerequisiteUnit = $condition->requiredUnit;

                    $conditionNode = [
                        'condition_id' => $condition->id,
                        'type' => $condition->type,
                        'unit' => [
                            'id' => $prerequisiteUnit->id,
                            'code' => $prerequisiteUnit->code,
                            'name' => $prerequisiteUnit->name,
                        ],
                        'children' => $this->getPrerequisiteTree($prerequisiteUnit, $depth + 1, $maxDepth),
                    ];
                } else {
                    $conditionNode = [
                        'condition_id' => $condition->id,
                        'type' => $condition->type,
                        'required_credits' => $condition->required_credits,
                        'free_text' => $condition->free_text,
                        'children' => [],
                    ];
                }

                $groupNode['conditions'][] = $conditionNode;
            }

            $tree[] = $groupNode;
        }

        return $tree;
    }

    /**
     * Get units that have this unit as a prerequisite (reverse lookup)
     */
    public function getUnitsRequiringAsPrerequisite(Unit $unit): Collection
    {
        return UnitPrerequisiteCondition::where('required_unit_id', $unit->id)
            ->where('type', 'prerequisite')
            ->with(['group.unit'])
            ->get()
            ->pluck('group.unit')
            ->filter()
            ->unique('id');
    }

    /**
     * Check concurrent enrollment eligibility
     */
    public function canEnrollConcurrently(Student $student, CourseOffering $courseOffering, CourseOffering $prerequisiteCourseOffering): bool
    {
        $unit = $courseOffering->unit;
        $prerequisiteUnit = $prerequisiteCourseOffering->unit;

        // Check if the prerequisite unit is required by any condition in any group for this unit
        $condition = UnitPrerequisiteCondition::whereHas('group', function ($query) use ($unit) {
            $query->where('unit_id', $unit->id);
        })
            ->where('type', 'prerequisite')
            ->where('required_unit_id', $prerequisiteUnit->id)
            ->first();

        // For now, assume concurrent enrollment is not allowed unless explicitly configured
        // This could be extended with a field in UnitPrerequisiteCondition for allow_concurrent
        return false;
    }

    /**
     * Get recommended study sequence based on prerequisites
     */
    public function getRecommendedSequence(Student $student, Collection $units): array
    {
        $sequence = [];
        $completed = $this->getCompletedUnits($student);
        $remaining = $units->reject(function ($unit) use ($completed) {
            return $completed->contains('id', $unit->id);
        });

        $level = 1;
        while ($remaining->isNotEmpty() && $level <= 10) {
            $availableThisLevel = $remaining->filter(function ($unit) use ($completed) {
                $prerequisiteGroups = $this->getPrerequisiteGroups($unit);

                if ($prerequisiteGroups->isEmpty()) {
                    return true;
                }

                // Check if all prerequisite groups are satisfied
                foreach ($prerequisiteGroups as $group) {
                    foreach ($group->conditions as $condition) {
                        if ($condition->type === 'prerequisite' && $condition->requiredUnit) {
                            if (! $completed->contains('id', $condition->requiredUnit->id)) {
                                return false;
                            }
                        }
                        // Could add more logic for other condition types
                    }
                }

                return true;
            });

            if ($availableThisLevel->isEmpty()) {
                break; // Circular dependency or missing prerequisites
            }

            $sequence[] = [
                'level' => $level,
                'semester' => ceil($level / 2),
                'units' => $availableThisLevel->values()->toArray(),
            ];

            // Add these units to completed for next iteration
            $completed = $completed->merge($availableThisLevel);
            $remaining = $remaining->reject(function ($unit) use ($availableThisLevel) {
                return $availableThisLevel->contains('id', $unit->id);
            });

            $level++;
        }

        // Add any remaining units that couldn't be sequenced
        if ($remaining->isNotEmpty()) {
            $sequence[] = [
                'level' => $level,
                'semester' => ceil($level / 2),
                'units' => $remaining->values()->toArray(),
                'note' => 'Units with unresolved prerequisites',
            ];
        }

        return $sequence;
    }

    /**
     * Get completed units for a student
     */
    protected function getCompletedUnits(Student $student): Collection
    {
        return $student->academicRecords()
            ->where('completion_status', 'completed')
            ->with('unit')
            ->get()
            ->pluck('unit')
            ->unique('id');
    }

    /**
     * Clear prerequisite cache for a unit
     */
    public function clearPrerequisiteCache(Unit $unit): void
    {
        $cacheKey = "unit_prerequisite_groups:{$unit->id}";
        Cache::forget($cacheKey);
    }
}
