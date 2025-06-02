<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssessmentComponent;
use App\Models\Semester;
use App\Models\Syllabus;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SyllabusController extends Controller
{
    /**
     * Display syllabus for a specific unit.
     */
    public function index(Unit $unit): Response
    {
        $syllabus = $unit->syllabus()
            ->with(['effectiveFromSemester', 'assessmentComponents.details'])
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $syllabus->each(function ($syllabus) {
            $syllabus->total_assessment_weight = $syllabus->assessmentComponents->sum('weight');
        });

        return Inertia::render('syllabus/Index', [
            'unit' => $unit,
            'syllabus' => $syllabus,
        ]);
    }

    /**
     * Show the form for creating a new syllabus.
     */
    public function create(Unit $unit): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('syllabus/Create', [
            'unit' => $unit,
            'semesters' => $semesters,
            'assessmentTypes' => AssessmentComponent::TYPES,
        ]);
    }

    /**
     * Store a newly created syllabus.
     */
    public function store(Request $request, Unit $unit)
    {
        // Debug: Log the incoming request data
        Log::info('Syllabus Store Request Data:', [
            'assessment_components' => $request->input('assessment_components'),
        ]);

        $validated = $request->validate([
            'version' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'total_hours' => 'nullable|numeric|min:0',
            'hours_per_session' => 'nullable|numeric|min:0',
            'effective_from_semester_id' => 'nullable|exists:semesters,id',
            'is_active' => 'boolean',
            'assessment_components' => 'nullable|array',
            'assessment_components.*.name' => 'required_with:assessment_components|string|max:100',
            'assessment_components.*.weight' => 'required_with:assessment_components|numeric|min:0|max:100',
            'assessment_components.*.type' => ['required_with:assessment_components', Rule::in(array_keys(AssessmentComponent::TYPES))],
            'assessment_components.*.is_required_to_sit_final_exam' => 'boolean',
            'assessment_components.*.details' => 'nullable|array',
            'assessment_components.*.details.*.name' => 'required_with:assessment_components.*.details|string|max:100',
            'assessment_components.*.details.*.weight' => 'required_with:assessment_components.*.details|numeric|min:0|max:100',
        ]);

        // Debug: Log the validated data
        Log::info('Syllabus Store Validated Data:', [
            'assessment_components' => $validated['assessment_components'] ?? null,
        ]);

        // Convert string numbers to actual numbers
        $validated['total_hours'] = $validated['total_hours'] ? (float) $validated['total_hours'] : null;
        $validated['hours_per_session'] = $validated['hours_per_session'] ? (float) $validated['hours_per_session'] : null;

        if (!empty($validated['assessment_components'])) {
            foreach ($validated['assessment_components'] as &$component) {
                $component['weight'] = (float) $component['weight'];

                if (!empty($component['details'])) {
                    foreach ($component['details'] as &$detail) {
                        $detail['weight'] = $detail['weight'] ? (float) $detail['weight'] : null;
                    }
                }
            }
        }

        try {
            DB::beginTransaction();

            // If marking as active, deactivate other syllabus for this unit
            if ($validated['is_active'] ?? false) {
                $unit->syllabus()->update(['is_active' => false]);
            }

            $syllabus = $unit->syllabus()->create([
                'version' => $validated['version'],
                'description' => $validated['description'],
                'total_hours' => $validated['total_hours'],
                'hours_per_session' => $validated['hours_per_session'],
                'effective_from_semester_id' => $validated['effective_from_semester_id'],
                'is_active' => $validated['is_active'] ?? false,
            ]);

            // Create assessment components
            if (!empty($validated['assessment_components'])) {
                foreach ($validated['assessment_components'] as $componentData) {
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

            DB::commit();

            return redirect()->route('syllabus.index', $unit)
                ->with('success', 'Syllabus created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create syllabus: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified syllabus.
     */
    public function show(Unit $unit, Syllabus $syllabus): Response
    {
        $syllabus->load([
            'effectiveFromSemester',
            'assessmentComponents.details',
        ]);

        $syllabus->total_assessment_weight = $syllabus->assessmentComponents->sum('weight');

        return Inertia::render('syllabus/Show', [
            'unit' => $unit,
            'syllabus' => $syllabus,
        ]);
    }

    /**
     * Show the form for editing the specified syllabus.
     */
    public function edit(Unit $unit, Syllabus $syllabus): Response
    {
        $syllabus->load(['assessmentComponents.details']);

        $semesters = Semester::orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('syllabus/Edit', [
            'unit' => $unit,
            'syllabus' => $syllabus,
            'semesters' => $semesters,
            'assessmentTypes' => AssessmentComponent::TYPES,
        ]);
    }

    /**
     * Update the specified syllabus.
     */
    public function update(Request $request, Unit $unit, Syllabus $syllabus)
    {
        $validated = $request->validate([
            'version' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'total_hours' => 'nullable|numeric|min:0',
            'hours_per_session' => 'nullable|numeric|min:0',
            'effective_from_semester_id' => 'nullable|exists:semesters,id',
            'is_active' => 'boolean',
            'assessment_components' => 'nullable|array',
            'assessment_components.*.id' => 'nullable|exists:assessment_components,id',
            'assessment_components.*.name' => 'required_with:assessment_components|string|max:100',
            'assessment_components.*.weight' => 'required_with:assessment_components|numeric|min:0|max:100',
            'assessment_components.*.type' => ['required_with:assessment_components', Rule::in(array_keys(AssessmentComponent::TYPES))],
            'assessment_components.*.is_required_to_sit_final_exam' => 'boolean',
            'assessment_components.*.details' => 'nullable|array',
            'assessment_components.*.details.*.id' => 'nullable|exists:assessment_component_details,id',
            'assessment_components.*.details.*.name' => 'required_with:assessment_components.*.details|string|max:100',
            'assessment_components.*.details.*.weight' => 'required_with:assessment_components.*.details|numeric|min:0|max:100',
        ]);

        // Convert string numbers to actual numbers
        $validated['total_hours'] = $validated['total_hours'] ? (float) $validated['total_hours'] : null;
        $validated['hours_per_session'] = $validated['hours_per_session'] ? (float) $validated['hours_per_session'] : null;

        if (!empty($validated['assessment_components'])) {
            foreach ($validated['assessment_components'] as &$component) {
                $component['weight'] = (float) $component['weight'];

                if (!empty($component['details'])) {
                    foreach ($component['details'] as &$detail) {
                        $detail['weight'] = $detail['weight'] ? (float) $detail['weight'] : null;
                    }
                }
            }
        }

        Log::info('Syllabus Update Validated Data:', [
            'assessment_components' => $validated['assessment_components'] ?? null,
        ]);

        try {
            DB::beginTransaction();

            // If marking as active, deactivate other syllabus for this unit
            if ($validated['is_active'] ?? false) {
                $unit->syllabus()->where('id', '!=', $syllabus->id)->update(['is_active' => false]);
            }

            $syllabus->update([
                'version' => $validated['version'],
                'description' => $validated['description'],
                'total_hours' => $validated['total_hours'],
                'hours_per_session' => $validated['hours_per_session'],
                'effective_from_semester_id' => $validated['effective_from_semester_id'],
                'is_active' => $validated['is_active'] ?? false,
            ]);

            // Update assessment components
            if (isset($validated['assessment_components'])) {
                // Make a deep copy of the assessment components to prevent corruption
                $assessmentComponents = json_decode(json_encode($validated['assessment_components']), true);

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
                            Log::info('Updated existing component', ['component_id' => $component->id]);
                        } else {
                            // Component not found or doesn't belong to this syllabus, skip
                            Log::warning('Component not found or invalid', ['component_id' => $componentData['id']]);
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
                        Log::info('Created new component', ['component_id' => $component->id]);
                    }

                    // Handle component details using the original data to prevent corruption
                    if ($component && isset($originalComponentData['details']) && is_array($originalComponentData['details'])) {
                        Log::info('Processing component details', [
                            'component_id' => $component->id,
                            'details_count' => count($originalComponentData['details']),
                            'details' => $originalComponentData['details'],
                        ]);

                        $existingDetailIds = $component->details()->pluck('id')->toArray();
                        $providedDetailIds = collect($originalComponentData['details'])
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
                        foreach ($originalComponentData['details'] as $detailData) {
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
                    } else {
                        Log::info('Skipping details processing', [
                            'has_component' => $component !== null,
                            'has_details' => isset($originalComponentData['details']),
                            'details_is_array' => isset($originalComponentData['details']) ? is_array($originalComponentData['details']) : false,
                            'component_id' => $component ? $component->id : 'null',
                            'original_data_type' => gettype($originalComponentData['details'] ?? 'not_set'),
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('syllabus.index', $unit)
                ->with('success', 'Syllabus updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Syllabus update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return redirect()->back()
                ->withErrors(['error' => 'Failed to update syllabus: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified syllabus.
     */
    public function destroy(Unit $unit, Syllabus $syllabus)
    {
        try {
            $syllabus->delete();

            return redirect()->route('syllabus.index', $unit)
                ->with('success', 'Syllabus deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete syllabus: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle the active status of a syllabus.
     */
    public function toggleActive(Unit $unit, Syllabus $syllabus)
    {
        try {
            DB::beginTransaction();

            if (!$syllabus->is_active) {
                // Deactivate all other syllabus for this unit
                $unit->syllabus()->where('id', '!=', $syllabus->id)->update(['is_active' => false]);
                $syllabus->update(['is_active' => true]);
                $message = 'Syllabus activated successfully.';
            } else {
                $syllabus->update(['is_active' => false]);
                $message = 'Syllabus deactivated successfully.';
            }

            DB::commit();

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withErrors(['error' => 'Failed to toggle syllabus status: ' . $e->getMessage()]);
        }
    }

    /**
     * Clone an existing syllabus.
     */
    public function clone(Unit $unit, Syllabus $syllabus)
    {
        try {
            DB::beginTransaction();

            // Generate new version number
            $baseVersion = $syllabus->version ?: 'v1.0';
            $newVersion = $this->generateNewVersion($unit, $baseVersion);

            // Clone the syllabus
            $clonedSyllabus = $unit->syllabus()->create([
                'version' => $newVersion,
                'description' => $syllabus->description,
                'total_hours' => $syllabus->total_hours,
                'hours_per_session' => $syllabus->hours_per_session,
                'effective_from_semester_id' => $syllabus->effective_from_semester_id,
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

            DB::commit();

            return redirect()->route('syllabus.index', $unit)
                ->with('success', "Syllabus cloned successfully as version {$newVersion}.");
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withErrors(['error' => 'Failed to clone syllabus: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate a new version number for cloned syllabus.
     */
    private function generateNewVersion(Unit $unit, string $baseVersion): string
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
                if (!$unit->syllabus()->where('version', $newVersion)->exists()) {
                    return $newVersion;
                }
            }

            // If minor versions exhausted, increment major
            for ($newMajor = $major + 1; $newMajor <= 99; $newMajor++) {
                $newVersion = "v{$newMajor}.0";
                if (!$unit->syllabus()->where('version', $newVersion)->exists()) {
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
