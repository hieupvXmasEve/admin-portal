<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AssessmentComponent;
use App\Models\Semester;
use App\Models\Syllabus;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SyllabusController extends Controller
{
    /**
     * Display syllabi for a specific unit.
     */
    public function index(Unit $unit): Response
    {
        $syllabi = $unit->syllabi()
            ->with(['effectiveFromSemester', 'assessmentComponents.details'])
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $syllabi->each(function ($syllabus) {
            $syllabus->total_assessment_weight = $syllabus->assessmentComponents->sum('weight');
        });

        return Inertia::render('syllabi/Index', [
            'unit' => $unit,
            'syllabi' => $syllabi,
        ]);
    }

    /**
     * Show the form for creating a new syllabus.
     */
    public function create(Unit $unit): Response
    {
        $semesters = Semester::orderBy('academic_year', 'desc')
            ->get();

        return Inertia::render('syllabi/Create', [
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
        $validated = $request->validate([
            'version' => 'nullable|string|max:10',
            'description' => 'nullable|string',
            'total_hours' => 'nullable|integer|min:0',
            'hours_per_session' => 'nullable|integer|min:0',
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

        try {
            DB::beginTransaction();

            // If marking as active, deactivate other syllabi for this unit
            if ($validated['is_active'] ?? false) {
                $unit->syllabi()->update(['is_active' => false]);
            }

            $syllabus = $unit->syllabi()->create([
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

            return redirect()->route('syllabi.index', $unit)
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

        return Inertia::render('syllabi/Show', [
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

        $semesters = Semester::orderBy('academic_year', 'desc')
            ->get();

        return Inertia::render('syllabi/Edit', [
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
            'total_hours' => 'nullable|integer|min:0',
            'hours_per_session' => 'nullable|integer|min:0',
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

        try {
            DB::beginTransaction();

            // If marking as active, deactivate other syllabi for this unit
            if ($validated['is_active'] ?? false) {
                $unit->syllabi()->where('id', '!=', $syllabus->id)->update(['is_active' => false]);
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
                // Get existing component IDs
                $existingComponentIds = $syllabus->assessmentComponents()->pluck('id')->toArray();
                $providedComponentIds = collect($validated['assessment_components'])
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                // Delete components not in the request
                $toDelete = array_diff($existingComponentIds, $providedComponentIds);
                if (!empty($toDelete)) {
                    AssessmentComponent::whereIn('id', $toDelete)->delete();
                }

                // Update or create components
                foreach ($validated['assessment_components'] as $componentData) {
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
                        }
                    } else {
                        // Create new component
                        $component = $syllabus->assessmentComponents()->create([
                            'name' => $componentData['name'],
                            'weight' => $componentData['weight'],
                            'type' => $componentData['type'],
                            'is_required_to_sit_final_exam' => $componentData['is_required_to_sit_final_exam'] ?? true,
                        ]);
                    }

                    // Handle component details
                    if (isset($componentData['details'])) {
                        $existingDetailIds = $component->details()->pluck('id')->toArray();
                        $providedDetailIds = collect($componentData['details'])
                            ->pluck('id')
                            ->filter()
                            ->toArray();

                        // Delete details not in the request
                        $toDeleteDetails = array_diff($existingDetailIds, $providedDetailIds);
                        if (!empty($toDeleteDetails)) {
                            $component->details()->whereIn('id', $toDeleteDetails)->delete();
                        }

                        // Update or create details
                        foreach ($componentData['details'] as $detailData) {
                            if (!empty($detailData['id'])) {
                                // Update existing detail
                                $detail = $component->details()->find($detailData['id']);
                                if ($detail) {
                                    $detail->update([
                                        'name' => $detailData['name'],
                                        'weight' => $detailData['weight'],
                                    ]);
                                }
                            } else {
                                // Create new detail
                                $component->details()->create([
                                    'name' => $detailData['name'],
                                    'weight' => $detailData['weight'],
                                ]);
                            }
                        }
                    }
                }
            }

            DB::commit();

            return redirect()->route('syllabi.index', $unit)
                ->with('success', 'Syllabus updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

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

            return redirect()->route('syllabi.index', $unit)
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
                // Deactivate all other syllabi for this unit
                $unit->syllabi()->where('id', '!=', $syllabus->id)->update(['is_active' => false]);
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
}
