<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Lecture\GetLectureTeachingDetailsAction;
use App\Actions\Lecture\GetTeachingHoursAction;
use App\Constants\LectureRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecture\ListLecturesRequest;
use App\Http\Requests\Lecture\StoreLectureRequest;
use App\Http\Requests\Lecture\UpdateLectureRequest;
use App\Http\Requests\Lecture\ViewLectureTeachingDetailsRequest;
use App\Http\Requests\Lecture\ViewTeachingHoursRequest;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\User;
use App\Queries\Lecture\ListLecturesQuery;
use App\Shared\Support\Enums\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
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
    public function index(ListLecturesRequest $request, ListLecturesQuery $query): Response
    {
        $currentCampusId = (int) session('current_campus_id');
        $validated = $request->validated();
        $filters = [
            'search' => $validated['search'] ?? '',
            'campus_id' => $validated['campus_id'] ?? 'all',
            'semester_id' => $this->resolveLectureSemesterFilter($validated['semester_id'] ?? null),
            'unit_type' => $validated['unit_type'] ?? 'all',
            'employment_status' => $validated['employment_status'] ?? 'all',
            'employment_type' => $validated['employment_type'] ?? 'all',
            'available_for_assignment' => $validated['available_for_assignment'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'sort' => $validated['sort'] ?? 'full_name',
            'direction' => $validated['direction'] ?? 'asc',
        ];

        $lectures = $query->handle($filters, $currentCampusId);

        return Inertia::render('lectures/Index', [
            'lectures' => $lectures,
            'filters' => $filters,
            'semesters' => Semester::select('id', 'name', 'code')
                ->orderBy('start_date', 'desc')
                ->get(),
            'unitTypeOptions' => $this->getLectureUnitTypeOptions($currentCampusId),
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

    private function resolveLectureSemesterFilter(null|string|int $semesterId): string
    {
        if ($semesterId !== null && $semesterId !== '') {
            return (string) $semesterId;
        }

        $activeSemester = Semester::getActiveSemester();

        return $activeSemester ? (string) $activeSemester->id : 'all';
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getLectureUnitTypeOptions(int $campusId): array
    {
        return CourseOffering::query()
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_offerings.campus_id', $campusId)
            ->whereNotNull('units.unit_type')
            ->distinct()
            ->orderBy('units.unit_type')
            ->pluck('units.unit_type')
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => $this->getUnitTypeLabel($type),
            ])
            ->values()
            ->all();
    }

    private function getUnitTypeLabel(string $type): string
    {
        return [
            'general' => 'General',
            'egc' => 'English Global Citizen',
            'semi' => 'Semiconductor',
            'ai' => 'Artificial Intelligence',
            'mkt' => 'Marketing',
            'ba' => 'Business Administration',
            'cs' => 'Computer Science',
            'ee' => 'Electrical Engineering',
            'me' => 'Mechanical Engineering',
            'fin' => 'Finance',
        ][$type] ?? ucfirst($type);
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
        $validated = $request->validated();

        DB::transaction(function () use ($validated, &$lecture) {
            // Extract password if provided (remove from validated to avoid storing in Lecture)
            $password = $validated['password'] ?? null;
            unset($validated['password']);

            // Create or find User record
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($password ?? Str::random(16)),
                    'type' => UserType::LECTURER,
                    'status' => User::STATUS_ACTIVE,
                ]
            );

            // Update user type if it was created with different type
            if ($user->type !== UserType::LECTURER) {
                $user->update(['type' => UserType::LECTURER]);
            }

            // Update user fields from lecture data
            $user->update([
                'name' => trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
                'phone' => $validated['phone'] ?? $user->phone,
            ]);

            // Update password if provided
            if ($password !== null) {
                $user->update(['password' => Hash::make($password)]);
            }

            // Set user_id in validated data
            $validated['user_id'] = $user->id;

            // Create lecture record
            $lecture = Lecture::create($validated);
        });

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
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $lecture) {
            // Extract password if provided (remove from validated to avoid storing in Lecture)
            $password = $validated['password'] ?? null;
            unset($validated['password']);

            // Get or create User record
            $user = $lecture->user;

            if (! $user) {
                // Create new User if doesn't exist
                $user = User::create([
                    'name' => trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($password ?? Str::random(16)),
                    'type' => UserType::LECTURER,
                    'status' => User::STATUS_ACTIVE,
                ]);
                $validated['user_id'] = $user->id;
            } else {
                // Update existing User
                $user->update([
                    'name' => trim(($validated['first_name'] ?? '').' '.($validated['last_name'] ?? '')),
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? $user->phone,
                    'type' => UserType::LECTURER, // Ensure type is lecturer
                ]);

                // Update password if provided
                if ($password !== null) {
                    $user->update(['password' => Hash::make($password)]);
                }
            }

            // Update lecture record
            $lecture->update($validated);
        });

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
    public function teachingHours(ViewTeachingHoursRequest $request, GetTeachingHoursAction $action): Response
    {
        $currentCampusId = session('current_campus_id');

        $validated = $request->validated();

        $results = $action->execute($validated, (int) $currentCampusId);

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

    /**
     * Display detailed teaching hours for a specific lecturer.
     */
    public function showTeachingHours(Lecture $lecture, ViewLectureTeachingDetailsRequest $request, GetLectureTeachingDetailsAction $action): Response
    {
        $validated = $request->validated();

        // Set default dates if not provided
        if (! isset($validated['date_from'])) {
            $validated['date_from'] = now()->subMonth()->day(16)->format('Y-m-d');
        }
        if (! isset($validated['date_to'])) {
            $validated['date_to'] = now()->day(15)->format('Y-m-d');
        }

        $results = $action->execute($lecture->id, $validated);

        // Unit types for filter
        $unitTypes = [
            ['value' => 'general', 'label' => 'General'],
            ['value' => 'egc', 'label' => 'EGC'],
            ['value' => 'semi', 'label' => 'Semi'],
            ['value' => 'ai', 'label' => 'AI'],
            ['value' => 'mkt', 'label' => 'Marketing'],
            ['value' => 'ba', 'label' => 'Business Analytics'],
            ['value' => 'cs', 'label' => 'Computer Science'],
            ['value' => 'ee', 'label' => 'Electrical Engineering'],
            ['value' => 'me', 'label' => 'Mechanical Engineering'],
            ['value' => 'fin', 'label' => 'Finance'],
        ];

        return Inertia::render('lectures/TeachingHoursDetail', [
            'lecture' => $lecture->only(['id', 'first_name', 'last_name', 'email', 'employee_id']),
            'sessions' => $results['sessions'],
            'stats' => $results['stats'],
            'filters' => [
                'unit_type' => $validated['unit_type'] ?? 'all',
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'sort' => $validated['sort'] ?? 'date',
                'direction' => $validated['direction'] ?? 'desc',
                'per_page' => $validated['per_page'] ?? 15,
            ],
            'unitTypes' => $unitTypes,
        ]);
    }
}
