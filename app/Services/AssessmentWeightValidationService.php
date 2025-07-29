<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\CourseOffering;

class AssessmentWeightValidationService
{
    /**
     * Validate assessment weights for a specific course offering.
     */
    public function validateCourseWeights(CourseOffering $courseOffering): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return [
                'total_weight' => 0,
                'is_valid' => false,
                'exceeds_limit' => false,
                'missing_weight' => 100,
                'component_validations' => [],
                'errors' => ['No syllabus found for this course offering'],
            ];
        }

        // Get all assessment components for this syllabus
        $components = AssessmentComponent::where('syllabus_id', $syllabus->id)
            ->with('details')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        if ($components->isEmpty()) {
            return [
                'total_weight' => 0,
                'is_valid' => false,
                'exceeds_limit' => false,
                'missing_weight' => 100,
                'component_validations' => [],
                'errors' => ['No assessment components found for this course'],
            ];
        }

        $totalWeight = 0;
        $componentValidations = [];
        $globalErrors = [];

        foreach ($components as $component) {
            $componentValidation = $this->validateComponentWeights($component);
            $componentValidations[] = $componentValidation;
            $totalWeight += $component->weight;
        }

        // Check if total weight exceeds 100%
        $exceedsLimit = $totalWeight > 100;
        if ($exceedsLimit) {
            $globalErrors[] = "Total assessment weight ({$totalWeight}%) exceeds 100%";
        }

        // Check if total weight is exactly 100%
        $isValid = $totalWeight == 100;
        if (! $isValid && ! $exceedsLimit) {
            if ($totalWeight < 100) {
                $globalErrors[] = "Total assessment weight ({$totalWeight}%) is less than 100%";
            }
        }

        // Calculate missing weight
        $missingWeight = max(0, 100 - $totalWeight);

        // Check individual component validations for overall validity
        $hasComponentErrors = collect($componentValidations)->contains(function ($validation) {
            return ! $validation['is_valid'];
        });

        if ($hasComponentErrors) {
            $isValid = false;
        }

        return [
            'total_weight' => $totalWeight,
            'is_valid' => $isValid && ! $hasComponentErrors && ! $exceedsLimit,
            'exceeds_limit' => $exceedsLimit,
            'missing_weight' => $missingWeight,
            'component_validations' => $componentValidations,
            'errors' => $globalErrors,
        ];
    }

    /**
     * Validate weights for a specific assessment component.
     */
    public function validateComponentWeights(AssessmentComponent $component): array
    {
        $componentData = [
            'assessment_id' => $component->id,
            'assessment_name' => $component->name,
            'current_weight' => $component->weight,
            'detail_weights_sum' => 0,
            'is_valid' => true,
            'errors' => [],
        ];

        // Validate component weight itself
        if ($component->weight <= 0) {
            $componentData['is_valid'] = false;
            $componentData['errors'][] = 'Component weight must be greater than 0';
        }

        if ($component->weight > 100) {
            $componentData['is_valid'] = false;
            $componentData['errors'][] = 'Component weight cannot exceed 100%';
        }

        // Get component details and validate their weights
        $details = $component->details;
        $detailWeightsSum = 0;

        if ($details->isNotEmpty()) {
            foreach ($details as $detail) {
                $detailWeightsSum += $detail->weight;

                // Validate individual detail weights
                if ($detail->weight <= 0) {
                    $componentData['is_valid'] = false;
                    $componentData['errors'][] = "Detail '{$detail->name}' weight must be greater than 0";
                }
            }

            $componentData['detail_weights_sum'] = $detailWeightsSum;

            // Check if detail weights exceed component weight
            if ($detailWeightsSum > $component->weight) {
                $componentData['is_valid'] = false;
                $componentData['errors'][] = "Sum of detail weights ({$detailWeightsSum}%) exceeds component weight ({$component->weight}%)";
            }

            // Check if detail weights are less than component weight (warning, not error)
            if ($detailWeightsSum < $component->weight && $detailWeightsSum > 0) {
                $unaccountedWeight = $component->weight - $detailWeightsSum;
                $componentData['errors'][] = "Sum of detail weights ({$detailWeightsSum}%) is less than component weight ({$component->weight}%) - {$unaccountedWeight}% unaccounted";
            }

            // Check if no details have weights but component has weight
            if ($detailWeightsSum == 0 && $component->weight > 0) {
                $componentData['errors'][] = 'Component has weight but no detail weights are set';
            }
        } else {
            // Component has no details - this might be valid for some assessment types
            $componentData['detail_weights_sum'] = 0;
        }

        return $componentData;
    }

    /**
     * Validate weight constraints before creating or updating components.
     *
     * @param  array  $componentData  New component data
     * @param  int|null  $excludeComponentId  Component ID to exclude from total (for updates)
     */
    public function validateNewComponentWeight(CourseOffering $courseOffering, array $componentData, ?int $excludeComponentId = null): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return [
                'is_valid' => false,
                'errors' => ['No syllabus found for this course offering'],
                'total_weight' => 0,
                'remaining_weight' => 0,
            ];
        }

        $newWeight = $componentData['weight'] ?? 0;

        // Validate the new component weight itself
        $errors = [];
        if ($newWeight <= 0) {
            $errors[] = 'Component weight must be greater than 0';
        }

        if ($newWeight > 100) {
            $errors[] = 'Component weight cannot exceed 100%';
        }

        // Get existing components (excluding the one being updated if applicable)
        $existingComponents = AssessmentComponent::where('syllabus_id', $syllabus->id)
            ->when($excludeComponentId, function ($query, $excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->get();

        $existingWeight = $existingComponents->sum('weight');
        $totalWeight = $existingWeight + $newWeight;

        if ($totalWeight > 100) {
            $excess = $totalWeight - 100;
            $errors[] = "Adding this component would exceed 100% total weight. Current total: {$existingWeight}%, New component: {$newWeight}%, Total would be: {$totalWeight}%";
        }

        $remainingWeight = max(0, 100 - $totalWeight);

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'total_weight' => $totalWeight,
            'remaining_weight' => $remainingWeight,
            'existing_weight' => $existingWeight,
            'new_weight' => $newWeight,
        ];
    }

    /**
     * Validate detail weight constraints for a component.
     *
     * @param  array  $detailData  New detail data
     * @param  int|null  $excludeDetailId  Detail ID to exclude from total (for updates)
     */
    public function validateNewDetailWeight(AssessmentComponent $component, array $detailData, ?int $excludeDetailId = null): array
    {
        $newWeight = $detailData['weight'] ?? 0;

        // Validate the new detail weight itself
        $errors = [];
        if ($newWeight <= 0) {
            $errors[] = 'Detail weight must be greater than 0';
        }

        // Get existing details (excluding the one being updated if applicable)
        $existingDetails = $component->details()
            ->when($excludeDetailId, function ($query, $excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->get();

        $existingWeight = $existingDetails->sum('weight');
        $totalDetailWeight = $existingWeight + $newWeight;

        if ($totalDetailWeight > $component->weight) {
            $errors[] = "Adding this detail would exceed component weight. Component weight: {$component->weight}%, Existing details: {$existingWeight}%, New detail: {$newWeight}%, Total would be: {$totalDetailWeight}%";
        }

        $remainingWeight = max(0, $component->weight - $totalDetailWeight);

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'total_detail_weight' => $totalDetailWeight,
            'remaining_weight' => $remainingWeight,
            'existing_weight' => $existingWeight,
            'new_weight' => $newWeight,
            'component_weight' => $component->weight,
        ];
    }

    /**
     * Get weight distribution summary for a course offering.
     */
    public function getWeightDistributionSummary(CourseOffering $courseOffering): array
    {
        $syllabus = $courseOffering->syllabus;

        if (! $syllabus) {
            return [
                'total_weight' => 0,
                'components_count' => 0,
                'weight_distribution' => [],
                'remaining_weight' => 100,
            ];
        }

        $components = AssessmentComponent::where('syllabus_id', $syllabus->id)
            ->with('details')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $totalWeight = 0;
        $weightDistribution = [];

        foreach ($components as $component) {
            $detailsWeight = $component->details->sum('weight');
            $componentData = [
                'id' => $component->id,
                'name' => $component->name,
                'type' => $component->type,
                'weight' => $component->weight,
                'details_count' => $component->details->count(),
                'details_weight_sum' => $detailsWeight,
                'details_weight_remaining' => max(0, $component->weight - $detailsWeight),
                'weight_percentage' => $components->sum('weight') > 0 ? round(($component->weight / $components->sum('weight')) * 100, 2) : 0,
            ];

            $weightDistribution[] = $componentData;
            $totalWeight += $component->weight;
        }

        return [
            'total_weight' => $totalWeight,
            'components_count' => $components->count(),
            'weight_distribution' => $weightDistribution,
            'remaining_weight' => max(0, 100 - $totalWeight),
            'is_complete' => $totalWeight == 100,
            'exceeds_limit' => $totalWeight > 100,
        ];
    }

    /**
     * Generate weight validation report for audit purposes.
     */
    public function generateWeightValidationReport(CourseOffering $courseOffering): array
    {
        $validation = $this->validateCourseWeights($courseOffering);
        $distribution = $this->getWeightDistributionSummary($courseOffering);

        return [
            'course_offering' => [
                'id' => $courseOffering->id,
                'course_code' => $courseOffering->course_code,
                'course_title' => $courseOffering->course_title,
                'section_code' => $courseOffering->section_code,
            ],
            'validation_timestamp' => now()->toISOString(),
            'validation_results' => $validation,
            'weight_distribution' => $distribution,
            'recommendations' => $this->generateRecommendations($validation, $distribution),
        ];
    }

    /**
     * Generate recommendations based on validation results.
     */
    private function generateRecommendations(array $validation, array $distribution): array
    {
        $recommendations = [];

        // Check for missing weight
        if ($validation['missing_weight'] > 0) {
            $recommendations[] = [
                'type' => 'missing_weight',
                'message' => "Add {$validation['missing_weight']}% more assessment weight to reach 100%",
                'severity' => 'warning',
            ];
        }

        // Check for exceeding weight
        if ($validation['exceeds_limit']) {
            $excessWeight = $validation['total_weight'] - 100;
            $recommendations[] = [
                'type' => 'exceeds_limit',
                'message' => "Reduce assessment weights by {$excessWeight}% to not exceed 100%",
                'severity' => 'error',
            ];
        }

        // Check for components with unaccounted weight
        foreach ($validation['component_validations'] as $componentValidation) {
            if (! $componentValidation['is_valid']) {
                foreach ($componentValidation['errors'] as $error) {
                    if (str_contains($error, 'unaccounted')) {
                        $recommendations[] = [
                            'type' => 'unaccounted_weight',
                            'message' => "Component '{$componentValidation['assessment_name']}': {$error}",
                            'severity' => 'warning',
                        ];
                    } elseif (str_contains($error, 'exceeds')) {
                        $recommendations[] = [
                            'type' => 'component_exceeds',
                            'message' => "Component '{$componentValidation['assessment_name']}': {$error}",
                            'severity' => 'error',
                        ];
                    }
                }
            }
        }

        // Check for no assessments
        if ($distribution['components_count'] == 0) {
            $recommendations[] = [
                'type' => 'no_assessments',
                'message' => 'No assessment components found. Add assessment components to define course grading structure.',
                'severity' => 'error',
            ];
        }

        return $recommendations;
    }
}
