<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\SyllabusRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Syllabus\StoreSyllabusRequest;
use App\Http\Requests\Syllabus\UpdateSyllabusRequest;
use App\Models\AssessmentComponent;
use App\Models\Syllabus;
use App\Models\Unit;
use App\Services\SyllabusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SyllabusController extends Controller
{
    protected $syllabusService;

    public function __construct(SyllabusService $syllabusService)
    {
        $this->syllabusService = $syllabusService;
    }

    /**
     * Display syllabus for a specific unit.
     */
    public function index(Unit $unit): Response
    {
        $syllabus = $unit->syllabus()
            ->with([
                'curriculumUnit.semester',
                'curriculumUnit.curriculumVersion.program',
                'curriculumUnit.curriculumVersion.specialization',
                'assessmentComponents.details',
            ])
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
        $curriculumUnits = $unit->curriculumUnits()
            ->with(['semester', 'curriculumVersion.specialization', 'curriculumVersion.program'])
            ->get();

        return Inertia::render('syllabus/Create', [
            'unit' => $unit,
            'curriculumUnits' => $curriculumUnits,
            'assessmentTypes' => AssessmentComponent::TYPES,
        ]);
    }

    /**
     * Store a newly created syllabus.
     */
    public function store(StoreSyllabusRequest $request, Unit $unit)
    {
        // Debug: Log the incoming request data
        Log::info('Syllabus Store Request Data:', [
            'assessment_components' => $request->input('assessment_components'),
        ]);

        $validated = $request->validated();

        // Debug: Log the validated data
        Log::info('Syllabus Store Validated Data:', [
            'assessment_components' => $validated['assessment_components'] ?? null,
        ]);

        try {
            $syllabus = $this->syllabusService->create($validated);

            return redirect()->route(SyllabusRoutes::INDEX, $unit)
                ->with('success', 'Syllabus created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to create syllabus: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified syllabus.
     */
    public function show(Unit $unit, Syllabus $syllabus): Response
    {
        $syllabus->load([
            'curriculumUnit.semester',
            'curriculumUnit.curriculumVersion.program',
            'curriculumUnit.curriculumVersion.specialization',
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
        $syllabus->load([
            'assessmentComponents.details',
            'curriculumUnit.semester',
            'curriculumUnit.curriculumVersion.program',
            'curriculumUnit.curriculumVersion.specialization',
        ]);

        $curriculumUnits = $unit->curriculumUnits()
            ->with([
                'semester',
                'curriculumVersion.program',
                'curriculumVersion.specialization',
            ])
            ->get();

        return Inertia::render('syllabus/Edit', [
            'unit' => $unit,
            'syllabus' => $syllabus,
            'curriculumUnits' => $curriculumUnits,
            'assessmentTypes' => AssessmentComponent::TYPES,
        ]);
    }

    /**
     * Update the specified syllabus.
     */
    public function update(UpdateSyllabusRequest $request, Unit $unit, Syllabus $syllabus)
    {
        $validated = $request->validated();

        Log::info('Syllabus Update Validated Data:', [
            'assessment_components' => $validated['assessment_components'] ?? null,
        ]);

        try {
            $updatedSyllabus = $this->syllabusService->update($syllabus, $validated);

            return redirect()->route(SyllabusRoutes::INDEX, $unit)
                ->with('success', 'Syllabus updated successfully.');
        } catch (\Exception $e) {
            Log::error('Syllabus update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return redirect()->back()
                ->withErrors(['error' => 'Failed to update syllabus: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified syllabus.
     */
    public function destroy(Unit $unit, Syllabus $syllabus)
    {
        try {
            $this->syllabusService->delete($syllabus);

            return redirect()->route(SyllabusRoutes::INDEX, $unit)
                ->with('success', 'Syllabus deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to delete syllabus: '.$e->getMessage()]);
        }
    }

    /**
     * Toggle the active status of a syllabus.
     */
    public function toggleActive(Unit $unit, Syllabus $syllabus)
    {
        try {
            $updatedSyllabus = $this->syllabusService->toggleActive($syllabus);

            $message = $updatedSyllabus->is_active
                ? 'Syllabus activated successfully.'
                : 'Syllabus deactivated successfully.';

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to toggle syllabus status: '.$e->getMessage()]);
        }
    }

    /**
     * Clone an existing syllabus.
     */
    public function clone(Unit $unit, Syllabus $syllabus)
    {
        try {
            $clonedSyllabus = $this->syllabusService->clone($syllabus);

            return redirect()->route(SyllabusRoutes::INDEX, $unit)
                ->with('success', "Syllabus cloned successfully as version {$clonedSyllabus->version}.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to clone syllabus: '.$e->getMessage()]);
        }
    }
}
