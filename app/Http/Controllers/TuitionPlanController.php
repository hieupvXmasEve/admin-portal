<?php

namespace App\Http\Controllers;

use App\Http\Requests\TuitionPlanRequest;
use App\Models\TuitionPlan;
use App\Models\CurriculumVersion;
use App\Models\Semester;
use App\Services\TuitionPlanService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TuitionPlanController extends Controller
{
    public function __construct(
        private TuitionPlanService $tuitionPlanService
    ) {}

    /**
     * Display a listing of tuition plans.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'curriculum_version_id' => 'nullable|integer|exists:curriculum_versions,id',
            'intake_semester_id' => 'nullable|integer|exists:semesters,id',
            'status' => 'nullable|string|in:all,active,inactive',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = TuitionPlan::query()
            ->with(['curriculumVersion.program', 'intakeSemester'])
            ->withCount('terms');

        // Apply search filter
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->whereHas('curriculumVersion', function ($q) use ($validated) {
                    $q->where('name', 'like', "%{$validated['search']}%");
                })
                ->orWhereHas('curriculumVersion.program', function ($q) use ($validated) {
                    $q->where('name', 'like', "%{$validated['search']}%");
                });
            });
        }

        // Apply curriculum version filter
        if (!empty($validated['curriculum_version_id'])) {
            $query->where('curriculum_version_id', $validated['curriculum_version_id']);
        }

        // Apply intake semester filter
        if (!empty($validated['intake_semester_id'])) {
            $query->where('intake_semester_id', $validated['intake_semester_id']);
        }

        // Apply status filter (ignore 'all')
        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            if ($validated['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($validated['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $tuitionPlans = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        // Get curriculum versions and semesters for filters
        $curriculumVersions = CurriculumVersion::with('program')
            ->orderBy('created_at', 'desc')
            ->get();

        $semesters = Semester::orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('TuitionPlans/Index', [
            'tuitionPlans' => $tuitionPlans,
            'curriculumVersions' => $curriculumVersions,
            'semesters' => $semesters,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'curriculum_version_id' => $validated['curriculum_version_id'] ?? null,
                'intake_semester_id' => $validated['intake_semester_id'] ?? null,
                'status' => $validated['status'] ?? 'all',
            ],
        ]);
    }

    /**
     * Show the form for creating a new tuition plan.
     */
    public function create(): Response
    {
        $curriculumVersions = CurriculumVersion::with('program')
            ->orderBy('created_at', 'desc')
            ->get();

        $semesters = Semester::orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('TuitionPlans/Create', [
            'curriculumVersions' => $curriculumVersions,
            'semesters' => $semesters,
        ]);
    }

    /**
     * Store a newly created tuition plan.
     */
    public function store(TuitionPlanRequest $request)
    {
        try {
            $tuitionPlan = $this->tuitionPlanService->createTuitionPlan($request->validated());

            return redirect()->route('tuition-plans.show', $tuitionPlan)
                ->with('success', 'Tuition plan created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified tuition plan.
     */
    public function show(TuitionPlan $tuitionPlan): Response
    {
        $tuitionPlan->load([
            'curriculumVersion.program',
            'intakeSemester',
            'terms.semester'
        ]);

        return Inertia::render('TuitionPlans/Show', [
            'tuitionPlan' => $tuitionPlan,
        ]);
    }

    /**
     * Show the form for editing the specified tuition plan.
     */
    public function edit(TuitionPlan $tuitionPlan): Response
    {
        $tuitionPlan->load(['terms']);

        $curriculumVersions = CurriculumVersion::with('program')
            ->orderBy('created_at', 'desc')
            ->get();

        $semesters = Semester::orderBy('start_date', 'desc')
            ->get();

        // Convert tuition plan to array and cast amounts to float for frontend
        $tuitionPlanData = $tuitionPlan->toArray();
        $tuitionPlanData['terms'] = array_map(function ($term) {
            $term['amount'] = (float) $term['amount'];
            return $term;
        }, $tuitionPlanData['terms']);

        return Inertia::render('TuitionPlans/Edit', [
            'tuitionPlan' => $tuitionPlanData,
            'curriculumVersions' => $curriculumVersions,
            'semesters' => $semesters,
        ]);
    }

    /**
     * Update the specified tuition plan.
     */
    public function update(TuitionPlanRequest $request, TuitionPlan $tuitionPlan)
    {
        try {
            $this->tuitionPlanService->updateTuitionPlan($tuitionPlan->id, $request->validated());

            return redirect()->route('tuition-plans.show', $tuitionPlan)
                ->with('success', 'Tuition plan updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified tuition plan.
     */
    public function destroy(TuitionPlan $tuitionPlan)
    {
        try {
            $this->tuitionPlanService->deleteTuitionPlan($tuitionPlan->id);

            return redirect()->route('tuition-plans.index')
                ->with('success', 'Tuition plan deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
