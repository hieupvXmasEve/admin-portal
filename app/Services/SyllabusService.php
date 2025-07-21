<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\Syllabus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyllabusService
{
    /**
     * Create a new syllabus with assessment components.
     */
    public function create(array $data): Syllabus
    {
        return DB::transaction(function () use ($data) {
            // If marking as active, deactivate other syllabus for this curriculum unit
            if ($data['is_active'] ?? false) {
                Syllabus::where('curriculum_unit_id', $data['curriculum_unit_id'])
                    ->update(['is_active' => false]);
            }

            $syllabus = Syllabus::create([
                'curriculum_unit_id' => $data['curriculum_unit_id'],
                'version' => $data['version'],
                'description' => $data['description'],
                'total_hours' => $data['total_hours'],
                'hours_per_session' => $data['hours_per_session'],
                'is_active' => $data['is_active'] ?? false,
            ]);

            // Create assessment components if provided
            if (!empty($data['assessment_components'])) {
                $this->createAssessmentComponents($syllabus, $data['assessment_components']);
            }

            return $syllabus;
        });
    }

    /**
     * Update an existing syllabus.
     */
    public function update(Syllabus $syllabus, array $data): Syllabus
    {
        return DB::transaction(function () use ($syllabus, $data) {
            // If marking as active, deactivate other syllabus for this curriculum unit
            if ($data['is_active'] ?? false) {
                Syllabus::where('curriculum_unit_id', $syllabus->curriculum_unit_id)
                    ->where('id', '!=', $syllabus->id)
                    ->update(['is_active' => false]);
            }

            $syllabus->update([
                'version' => $data['version'],
                'description' => $data['description'],
                'total_hours' => $data['total_hours'],
                'hours_per_session' => $data['hours_per_session'],
                'is_active' => $data['is_active'] ?? false,
            ]);

            // Update assessment components if provided
            if (isset($data['assessment_components'])) {
                $this->updateAssessmentComponents($syllabus, $data['assessment_components']);
            }

            return $syllabus;
        });
    }

    /**
     * Delete a syllabus.
     */
    public function delete(Syllabus $syllabus): bool
    {
        return DB::transaction(function () use ($syllabus) {
            return $syllabus->delete();
        });
    }

    /**
     * Toggle the active status of a syllabus.
     */
    public function toggleActive(Syllabus $syllabus): Syllabus
    {
        return DB::transaction(function () use ($syllabus) {
            if (!$syllabus->is_active) {
                // Deactivate all other syllabus for this curriculum unit
                Syllabus::where('curriculum_unit_id', $syllabus->curriculum_unit_id)
                    ->where('id', '!=', $syllabus->id)
                    ->update(['is_active' => false]);
                $syllabus->update(['is_active' => true]);
            } else {
                $syllabus->update(['is_active' => false]);
            }

            return $syllabus->fresh();
        });
    }

    /**
     * Clone an existing syllabus.
     */
    public function clone(Syllabus $syllabus): Syllabus
    {
        return DB::transaction(function () use ($syllabus) {
            // Generate new version number
            $baseVersion = $syllabus->version ?: 'v1.0';
            $newVersion = $this->generateNewVersionForCurriculumUnit($syllabus->curriculum_unit_id, $baseVersion);

            // Clone the syllabus
            $clonedSyllabus = Syllabus::create([
                'curriculum_unit_id' => $syllabus->curriculum_unit_id,
                'version' => $newVersion,
                'description' => $syllabus->description,
                'total_hours' => $syllabus->total_hours,
                'hours_per_session' => $syllabus->hours_per_session,
                'is_active' => false, // New clone is never active by default
            ]);

            // Clone assessment components
            foreach ($syllabus->assessmentComponents as $component) {
                $clonedComponent = $clonedSyllabus->assessmentComponents()->create([
                    'name' => $component->name,
                    'weight' => $component->weight,
                    'type' => $component->type,
                    'is_required_to_sit_final_exam' => $component->is_required_to_sit_final_exam,
                ]);

                // Clone component details
                foreach ($component->details as $detail) {
                    $clonedComponent->details()->create([
                        'name' => $detail->name,
                        'weight' => $detail->weight,
                    ]);
                }
            }

            return $clonedSyllabus;
        });
    }

    /**
     * Create assessment components for a syllabus.
     */
    private function createAssessmentComponents(Syllabus $syllabus, array $components): void
    {
        foreach ($components as $componentData) {
            $component = $syllabus->assessmentComponents()->create([
                'name' => $componentData['name'],
                'weight' => $componentData['weight'],
                'type' => $componentData['type'],
                'is_required_to_sit_final_exam' => $componentData['is_required_to_sit_final_exam'] ?? true,
            ]);

            // Create component details if provided
            if (!empty($componentData['details'])) {
                foreach ($componentData['details'] as $detailData) {
                    $component->details()->create([
                        'name' => $detailData['name'],
                        'weight' => $detailData['weight'],
                    ]);
                }
            }
        }
    }

    /**
     * Update assessment components for a syllabus.
     */
    private function updateAssessmentComponents(Syllabus $syllabus, array $components): void
    {
        // Make a deep copy of the assessment components to prevent corruption
        $assessmentComponents = json_decode(json_encode($components), true);

        Log::info('Assessment components after deep copy:', [
            'components' => $assessmentComponents,
        ]);

        // Get existing component IDs
        $existingComponentIds = $syllabus->assessmentComponents()->pluck('id')->toArray();
        $providedComponentIds = collect($assessmentComponents)
            ->pluck('id')
            ->filter()
            ->toArray();

        // Delete components not in the request
        $toDelete = array_diff($existingComponentIds, $providedComponentIds);
        if (!empty($toDelete)) {
            AssessmentComponent::whereIn('id', $toDelete)->delete();
        }

        // Update or create components
        foreach ($assessmentComponents as $componentIndex => $componentData) {
            Log::info('Processing component', [
                'component_index' => $componentIndex,
                'component_data' => $componentData,
                'has_details' => isset($componentData['details']),
                'details_count' => isset($componentData['details']) ? count($componentData['details']) : 0,
            ]);

            $component = null;

            // Store the original component data to prevent corruption
            $originalComponentData = $componentData;

            if (!empty($componentData['id'])) {
                // Update existing component
                $component = AssessmentComponent::find($componentData['id']);
                if ($component && $component->syllabus_id === $syllabus->id) {
                    $component->update([
                        'name' => $componentData['name'],
                        'weight' => $componentData['weight'],
                        'type' => $componentData['type'],
                        'is_required_to_sit_final_exam' => $componentData['is_required_to_sit_final_exam'] ?? true,
                    ]);
                    Log::info('Updated existing component', ['assessment_component_id' => $component->id]);
                } else {
                    // Component not found or doesn't belong to this syllabus, skip
                    Log::warning('Component not found or invalid', ['assessment_component_id' => $componentData['id']]);
                    continue;
                }
            } else {
                // Create new component
                $component = $syllabus->assessmentComponents()->create([
                    'name' => $componentData['name'],
                    'weight' => $componentData['weight'],
                    'type' => $componentData['type'],
                    'is_required_to_sit_final_exam' => $componentData['is_required_to_sit_final_exam'] ?? true,
                ]);
                Log::info('Created new component', ['assessment_component_id' => $component->id]);
            }

            // Handle component details using the original data to prevent corruption
            if ($component && isset($originalComponentData['details']) && is_array($originalComponentData['details'])) {
                $this->updateComponentDetails($component, $originalComponentData['details']);
            }
        }
    }

    /**
     * Update component details.
     */
    private function updateComponentDetails(AssessmentComponent $component, array $details): void
    {
        Log::info('Processing component details', [
            'assessment_component_id' => $component->id,
            'details_count' => count($details),
            'details' => $details,
        ]);

        $existingDetailIds = $component->details()->pluck('id')->toArray();
        $providedDetailIds = collect($details)
            ->pluck('id')
            ->filter()
            ->toArray();

        Log::info('Detail IDs comparison', [
            'existing_detail_ids' => $existingDetailIds,
            'provided_detail_ids' => $providedDetailIds,
        ]);

        // Delete details not in the request
        $toDeleteDetails = array_diff($existingDetailIds, $providedDetailIds);
        if (!empty($toDeleteDetails)) {
            Log::info('Deleting details', ['ids' => $toDeleteDetails]);
            $component->details()->whereIn('id', $toDeleteDetails)->delete();
        }

        // Update or create details
        foreach ($details as $detailData) {
            Log::info('Processing detail', ['detail' => $detailData]);

            if (!empty($detailData['id'])) {
                // Update existing detail
                $detail = $component->details()->find($detailData['id']);
                if ($detail) {
                    Log::info('Updating existing detail', ['id' => $detailData['id']]);
                    $detail->update([
                        'name' => $detailData['name'],
                        'weight' => $detailData['weight'],
                    ]);
                } else {
                    Log::warning('Detail not found for update', ['detail_id' => $detailData['id']]);
                }
            } else {
                // Create new detail
                Log::info('Creating new detail', ['data' => $detailData]);
                $newDetail = $component->details()->create([
                    'name' => $detailData['name'],
                    'weight' => $detailData['weight'],
                ]);
                Log::info('Created new detail', ['detail_id' => $newDetail->id]);
            }
        }
    }

    /**
     * Generate a new version number for cloned syllabus.
     */
    public function generateNewVersionForCurriculumUnit(int $curriculumUnitId, string $baseVersion): string
    {
        // Extract version pattern (e.g., v1.0 -> 1.0)
        $versionNumber = preg_replace('/^v/', '', $baseVersion);

        // Check if version is numeric (e.g., 1.0, 2.1)
        if (preg_match('/^(\d+)\.(\d+)$/', $versionNumber, $matches)) {
            $major = (int) $matches[1];
            $minor = (int) $matches[2];

            // Try incrementing minor version first
            for ($newMinor = $minor + 1; $newMinor <= 99; $newMinor++) {
                $newVersion = "v{$major}.{$newMinor}";
                if (!Syllabus::where('curriculum_unit_id', $curriculumUnitId)
                    ->where('version', $newVersion)->exists()) {
                    return $newVersion;
                }
            }

            // If minor versions exhausted, increment major
            for ($newMajor = $major + 1; $newMajor <= 99; $newMajor++) {
                $newVersion = "v{$newMajor}.0";
                if (!Syllabus::where('curriculum_unit_id', $curriculumUnitId)
                    ->where('version', $newVersion)->exists()) {
                    return $newVersion;
                }
            }
        }

        // Fallback: append timestamp or increment
        $timestamp = now()->format('Ymd-His');
        $fallbackVersion = "{$baseVersion}-copy-{$timestamp}";

        return $fallbackVersion;
    }
}
