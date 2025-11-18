<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\LectureRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecture\StoreLectureRequest;
use App\Http\Requests\Lecture\UpdateLectureRequest;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\Lecture;
use App\Models\Semester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

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

        return Inertia::render('lectures/Index', [
            'lectures' => $lectures,
            'filters' => $request->only([
                'search',
                'campus_id',
                'employment_status',
                'employment_type',
                'available_for_assignment',
            ]),
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
            'courseOfferings.unit',
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
     * API endpoint for getting lectures (for dropdowns, quick edits, etc.)
     */
    public function apiIndex(Request $request)
    {
        $validated = $request->validate([
            'campus_id' => 'nullable|exists:campuses,id',
            'is_active' => 'nullable|string|in:true,false,1,0',
            'is_available_for_assignment' => 'nullable|string|in:true,false,1,0',
            'employment_status' => 'nullable|string|in:active,on_leave,sabbatical,retired,terminated,suspended',
            'employment_type' => 'nullable|string|in:full_time,part_time,contract,visiting,emeritus',
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        // Convert string boolean values to actual booleans
        $isActive = isset($validated['is_active']) ? filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN) : null;
        $isAvailableForAssignment = isset($validated['is_available_for_assignment']) ? filter_var($validated['is_available_for_assignment'], FILTER_VALIDATE_BOOLEAN) : null;

        $query = Lecture::with(['campus'])
            ->where('campus_id', $validated['campus_id'] ?? session('current_campus_id'));

        // Apply filters using converted boolean values
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($isAvailableForAssignment !== null) {
            $query->where('is_available_for_assignment', $isAvailableForAssignment);
        }

        if (isset($validated['employment_status'])) {
            $query->where('employment_status', $validated['employment_status']);
        }

        if (isset($validated['employment_type'])) {
            $query->where('employment_type', $validated['employment_type']);
        }

        if (isset($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        // Default to active lecturers if not specified
        if ($isActive === null) {
            $query->where('is_active', true);
        }

        $lectures = $query->orderByName()
            ->limit($request->input('limit', 50))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lectures,
            'message' => 'Lecturers retrieved successfully',
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

    /**
     * Display teaching hours report for lecturers
     */
    public function teachingHours(Request $request): Response
    {
        $currentCampusId = session('current_campus_id');

        // Validate request parameters
        $validated = $request->validate([
            'semester_id' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'date_from' => 'nullable|date|after_or_equal:2025-01-01',
            'date_to' => 'nullable|date|before_or_equal:today',
            'sort' => 'nullable|string|in:name,hours',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Set default date_from to 2025-01-01 if not provided
        $dateFrom = $validated['date_from'] ?? '2025-01-01';
        $dateTo = $validated['date_to'] ?? now()->format('Y-m-d');

        // Ensure date_to is end of day (23:59:59)
        $dateToEndOfDay = \Carbon\Carbon::parse($dateTo)->endOfDay();

        // Build base query with joins
        $query = ClassSession::query()
            ->select([
                'lectures.id as lecture_id',
                'lectures.first_name',
                'lectures.last_name',
                'lectures.email',
                DB::raw('COUNT(class_sessions.id) as session_count'),
                DB::raw('SUM(COALESCE(class_sessions.duration_minutes, 
                    TIME_TO_SEC(TIMEDIFF(class_sessions.end_time, class_sessions.start_time)) / 60
                )) as total_minutes'),
            ])
            ->join('lectures', 'class_sessions.lecture_id', '=', 'lectures.id')
            ->join('course_offerings', 'class_sessions.course_offering_id', '=', 'course_offerings.id')
            ->where('lectures.campus_id', $currentCampusId)
            ->whereNotNull('class_sessions.lecture_id')
            ->where('class_sessions.session_date', '>=', $dateFrom)
            ->where('class_sessions.session_date', '<=', $dateToEndOfDay->format('Y-m-d'))
            ->groupBy('lectures.id', 'lectures.first_name', 'lectures.last_name', 'lectures.email');

        // Apply semester filter (skip if 'all' or empty)
        if ($request->filled('semester_id') && $validated['semester_id'] !== 'all') {
            $query->where('course_offerings.semester_id', $validated['semester_id']);
        }

        // Apply search filter (name or email)
        if ($request->filled('search')) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('lectures.first_name', 'like', "%{$search}%")
                    ->orWhere('lectures.last_name', 'like', "%{$search}%")
                    ->orWhere('lectures.email', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(lectures.first_name, ' ', lectures.last_name) LIKE ?", ["%{$search}%"]);
            });
        }

        // Apply sorting
        $sort = $validated['sort'] ?? 'name';
        $direction = $validated['direction'] ?? 'asc';

        if ($sort === 'name') {
            $query->orderBy('lectures.last_name', $direction)
                ->orderBy('lectures.first_name', $direction);
        } elseif ($sort === 'hours') {
            $query->orderBy('total_minutes', $direction);
        }

        // Get per_page or default to 15
        $perPage = $validated['per_page'] ?? 15;

        // Execute query and paginate
        $results = $query->paginate($perPage)->withQueryString();

        // Transform results to include total_hours
        $lecturers = $results->getCollection()->map(function ($item) {
            $totalMinutes = (int) $item->total_minutes;
            $totalHours = round($totalMinutes / 60, 2);

            return [
                'lecture_id' => $item->lecture_id,
                'lecture_name' => trim($item->first_name . ' ' . $item->last_name),
                'lecture_email' => $item->email,
                'total_hours' => $totalHours,
                'total_minutes' => $totalMinutes,
                'session_count' => (int) $item->session_count,
            ];
        });

        // Replace collection with transformed data
        $results->setCollection($lecturers);

        // Get semesters for filter dropdown
        $semesters = Semester::select('id', 'name', 'code', 'start_date', 'end_date')
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($semester) {
                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'code' => $semester->code,
                ];
            });

        return Inertia::render('lectures/TeachingHours', [
            'lecturers' => $results,
            'filters' => [
                'semester_id' => $validated['semester_id'] ?? 'all',
                'search' => $validated['search'] ?? '',
                'date_from' => $validated['date_from'] ?? '2025-01-01',
                'date_to' => $validated['date_to'] ?? now()->format('Y-m-d'),
                'sort' => $validated['sort'] ?? 'name',
                'direction' => $validated['direction'] ?? 'asc',
                'per_page' => $validated['per_page'] ?? 15,
            ],
            'semesters' => $semesters,
        ]);
    }
}
