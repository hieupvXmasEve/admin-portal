<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lecture\StoreLectureRequest;
use App\Http\Requests\Lecture\UpdateLectureRequest;
use App\Models\Lecture;
use App\Models\Campus;
use App\Constants\LectureRoutes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class LectureController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:view_lecturer')->only(['index', 'show']);
        // $this->middleware('can:create_lecturer')->only(['create', 'store']);
        // $this->middleware('can:edit_lecturer')->only(['edit', 'update']);
        // $this->middleware('can:delete_lecturer')->only(['destroy']);
    }

    /**
     * Display a listing of lectures
     */
    public function index(Request $request): Response
    {
        $currentCampusId = session('current_campus_id');
        $query = Lecture::with(['campus'])
            ->where('campus_id', $currentCampusId)
            ->orderByName();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('specialization', 'like', "%{$search}%");
            });
        }

        if ($request->filled('campus_id') && $request->campus_id !== 'all') {
            $query->where('campus_id', $request->campus_id);
        }

        if ($request->filled('employment_status') && $request->employment_status !== 'all') {
            $query->where('employment_status', $request->employment_status);
        }

        if ($request->filled('employment_type') && $request->employment_type !== 'all') {
            $query->where('employment_type', $request->employment_type);
        }

        if ($request->filled('department') && $request->department !== 'all') {
            $query->where('department', $request->department);
        }

        if ($request->filled('available_for_assignment')) {
            $query->where('is_available_for_assignment', $request->boolean('available_for_assignment'));
        }

        $lectures = $query->paginate(15)->withQueryString();

        // Get filter options

        $departments = Lecture::select('department')
            ->where('campus_id', $currentCampusId)
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return Inertia::render('lectures/Index', [
            'lectures' => $lectures,
            'filters' => $request->only([
                'search',
                'campus_id',
                'employment_status',
                'employment_type',
                'department',
                'available_for_assignment'
            ]),
            'departments' => $departments,
            'employmentStatusOptions' => [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'on_leave', 'label' => 'On Leave'],
                ['value' => 'sabbatical', 'label' => 'Sabbatical'],
                ['value' => 'retired', 'label' => 'Retired'],
                ['value' => 'terminated', 'label' => 'Terminated'],
                ['value' => 'suspended', 'label' => 'Suspended'],
            ],
            'employmentTypeOptions' => [
                ['value' => 'full_time', 'label' => 'Full Time'],
                ['value' => 'part_time', 'label' => 'Part Time'],
                ['value' => 'contract', 'label' => 'Contract'],
                ['value' => 'visiting', 'label' => 'Visiting'],
                ['value' => 'emeritus', 'label' => 'Emeritus'],
            ],
        ]);
    }

    /**
     * Show the form for creating a new lecture
     */
    public function create(): Response
    {
        $currentCampusId = session('current_campus_id');
        $campuses = Campus::orderBy('name')->get(['id', 'name']);

        return Inertia::render('lectures/Create', [
            'campuses' => $campuses,
            'currentCampusId' => $currentCampusId,
        ]);
    }

    /**
     * Store a newly created lecture
     */
    public function store(StoreLectureRequest $request): RedirectResponse
    {
        $lecture = Lecture::create($request->validated());

        return Redirect::route(LectureRoutes::INDEX)
            ->with('success', 'Lecturer created successfully.');
    }

    /**
     * Display the specified lecture
     */
    public function show(Lecture $lecture): Response
    {
        $lecture->load([
            'campus',
            'courseOfferings.semester',
            'courseOfferings.curriculumUnit.unit'
        ]);

        return Inertia::render('lectures/Show', [
            'lecture' => $lecture,
        ]);
    }

    /**
     * Show the form for editing the specified lecture
     */
    public function edit(Lecture $lecture): Response
    {
        $campuses = Campus::orderBy('name')->get(['id', 'name']);

        return Inertia::render('lectures/Edit', [
            'lecture' => $lecture,
            'campuses' => $campuses,
        ]);
    }

    /**
     * Update the specified lecture
     */
    public function update(UpdateLectureRequest $request, Lecture $lecture): RedirectResponse
    {
        $lecture->update($request->validated());

        return Redirect::route(LectureRoutes::INDEX)
            ->with('success', 'Lecturer updated successfully.');
    }

    /**
     * Remove the specified lecture
     */
    public function destroy(Lecture $lecture): RedirectResponse
    {
        // Check if lecture has any course offerings assigned
        if ($lecture->courseOfferings()->count() > 0) {
            return Redirect::back()
                ->with('error', 'Cannot delete lecture with assigned course offerings.');
        }

        $lecture->delete();

        return Redirect::route(LectureRoutes::INDEX)
            ->with('success', 'Lecture deleted successfully.');
    }

    /**
     * Get lectures for API/AJAX calls
     */
    public function apiSearch(Request $request)
    {
        $request->validate([
            'query' => 'nullable|string|min:2',
            'campus_id' => 'nullable|exists:campuses,id',
            'available_only' => 'boolean',
            'limit' => 'integer|min:1|max:50',
        ]);

        $query = Lecture::with(['campus'])
            ->active();

        if ($request->filled('query')) {
            $search = $request->query;
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('campus_id')) {
            $query->where('campus_id', $request->campus_id);
        }

        if ($request->boolean('available_only')) {
            $query->availableForAssignment();
        }

        $lectures = $query->orderByName()
            ->limit($request->get('limit', 20))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lectures,
            'message' => 'Lectures retrieved successfully',
        ]);
    }

    /**
     * Get lecture statistics
     */
    public function statistics(Request $request)
    {
        $campusId = $request->campus_id;

        $query = Lecture::query();

        if ($campusId && $campusId !== 'all') {
            $query->where('campus_id', $campusId);
        }

        $stats = [
            'total_lectures' => $query->count(),
            'active_lectures' => (clone $query)->active()->count(),
            'available_for_assignment' => (clone $query)->availableForAssignment()->count(),
            'full_time_lectures' => (clone $query)->fullTime()->count(),
            'part_time_lectures' => (clone $query)->partTime()->count(),
            'contract_lectures' => (clone $query)->contract()->count(),
            'lecturers' => (clone $query)->where('academic_rank', 'lecturer')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
