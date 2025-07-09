<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
        $this->middleware('can:view_lecture')->only(['index', 'show']);
        $this->middleware('can:create_lecture')->only(['create', 'store']);
        $this->middleware('can:edit_lecture')->only(['edit', 'update']);
        $this->middleware('can:delete_lecture')->only(['destroy']);
    }

    /**
     * Display a listing of lectures
     */
    public function index(Request $request): Response
    {
        $query = Lecture::with(['campus'])
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

        if ($request->filled('academic_rank') && $request->academic_rank !== 'all') {
            $query->where('academic_rank', $request->academic_rank);
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
        $campuses = Campus::orderBy('name')->get(['id', 'name']);
        $departments = Lecture::select('department')
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return Inertia::render('lectures/Index', [
            'lectures' => $lectures,
            'filters' => $request->only([
                'search',
                'campus_id',
                'academic_rank',
                'employment_status',
                'employment_type',
                'department',
                'available_for_assignment'
            ]),
            'campuses' => $campuses,
            'departments' => $departments,
            'academicRankOptions' => [
                ['value' => 'lecturer', 'label' => 'Lecturer'],
                ['value' => 'senior_lecturer', 'label' => 'Senior Lecturer'],
                ['value' => 'associate_professor', 'label' => 'Associate Professor'],
                ['value' => 'professor', 'label' => 'Professor'],
                ['value' => 'emeritus_professor', 'label' => 'Emeritus Professor'],
                ['value' => 'visiting_lecturer', 'label' => 'Visiting Lecturer'],
                ['value' => 'adjunct_professor', 'label' => 'Adjunct Professor'],
            ],
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
        $campuses = Campus::orderBy('name')->get(['id', 'name']);

        return Inertia::render('lectures/Create', [
            'campuses' => $campuses,
        ]);
    }

    /**
     * Store a newly created lecture
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(Lecture::validationRules(), Lecture::validationMessages());

        $lecture = Lecture::create($request->all());

        return Redirect::route(LectureRoutes::INDEX)
            ->with('success', 'Lecture created successfully.');
    }

    /**
     * Display the specified lecture
     */
    public function show(Lecture $lecture): Response
    {
        $lecture->load([
            'campus',
            'courseOfferings.semester',
            'courseOfferings.unit'
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
    public function update(Request $request, Lecture $lecture): RedirectResponse
    {
        $rules = Lecture::validationRules();

        // Update unique validation rules for existing record
        $rules['employee_id'] = ['required', 'string', 'max:20', 'unique:lectures,employee_id,' . $lecture->id];
        $rules['email'] = ['required', 'email', 'unique:lectures,email,' . $lecture->id];

        $request->validate($rules, Lecture::validationMessages());

        $lecture->update($request->all());

        return Redirect::route(LectureRoutes::INDEX)
            ->with('success', 'Lecture updated successfully.');
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
            'professors' => (clone $query)->where('academic_rank', 'professor')->count(),
            'associate_professors' => (clone $query)->where('academic_rank', 'associate_professor')->count(),
            'senior_lecturers' => (clone $query)->where('academic_rank', 'senior_lecturer')->count(),
            'lecturers' => (clone $query)->where('academic_rank', 'lecturer')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
