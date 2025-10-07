<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScholarshipRequest;
use App\Models\ScholarshipDefinition;
use App\Services\ScholarshipService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScholarshipController extends Controller
{
    public function __construct(
        private ScholarshipService $scholarshipService
    ) {}

    /**
     * Display a listing of scholarships.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:all,active,inactive,expired,valid',
            'type' => 'nullable|string|in:all,percentage,fixed_amount',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = ScholarshipDefinition::query()
            ->withCount('studentScholarshipAwards as student_count');

        // Apply search filter
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('code', 'like', "%{$validated['search']}%")
                    ->orWhere('name', 'like', "%{$validated['search']}%");
            });
        }

        // Apply status filter (ignore 'all')
        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            if ($validated['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($validated['status'] === 'inactive') {
                $query->where('is_active', false);
            } elseif ($validated['status'] === 'expired') {
                $query->where('valid_until', '<', now());
            } elseif ($validated['status'] === 'valid') {
                $query->where('valid_from', '<=', now())
                    ->where('valid_until', '>=', now())
                    ->where('is_active', true);
            }
        }

        // Apply type filter (ignore 'all')
        if (!empty($validated['type']) && $validated['type'] !== 'all') {
            $query->where('type', $validated['type']);
        }

        $scholarships = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        return Inertia::render('Scholarships/Index', [
            'scholarships' => $scholarships,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? 'all',
                'type' => $validated['type'] ?? 'all',
                'per_page' => $validated['per_page'] ?? 20,
            ],
            'can' => [
                'create' => $request->user()->can('scholarships.create'),
                'import' => $request->user()->can('scholarships.import'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new scholarship.
     */
    public function create(): Response
    {
        return Inertia::render('Scholarships/Create');
    }

    /**
     * Store a newly created scholarship.
     */
    public function store(ScholarshipRequest $request)
    {
        try {
            $scholarship = $this->scholarshipService->createScholarship($request->validated());

            return redirect()->route('scholarships.show', $scholarship)
                ->with('success', 'Scholarship created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified scholarship.
     */
    public function show(ScholarshipDefinition $scholarship): Response
    {
        $scholarship->load(['studentScholarshipAwards.student']);

        return Inertia::render('Scholarships/Show', [
            'scholarship' => $scholarship,
            'students' => $scholarship->studentScholarshipAwards,
            'can' => [
                'update' => request()->user()->can('scholarships.update'),
                'delete' => request()->user()->can('scholarships.delete'),
            ]
        ]);
    }

    /**
     * Show the form for editing the specified scholarship.
     */
    public function edit(ScholarshipDefinition $scholarship): Response
    {
        return Inertia::render('Scholarships/Edit', [
            'scholarship' => $scholarship
        ]);
    }

    /**
     * Update the specified scholarship.
     */
    public function update(ScholarshipRequest $request, ScholarshipDefinition $scholarship)
    {
        try {
            $this->scholarshipService->updateScholarship($scholarship->id, $request->validated());

            return redirect()->route('scholarships.show', $scholarship)
                ->with('success', 'Scholarship updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified scholarship.
     */
    public function destroy(ScholarshipDefinition $scholarship)
    {
        try {
            $this->scholarshipService->deleteScholarship($scholarship->id);

            return redirect()->route('scholarships.index')
                ->with('success', 'Scholarship deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
