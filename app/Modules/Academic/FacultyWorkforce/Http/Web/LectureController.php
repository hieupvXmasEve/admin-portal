<?php

declare(strict_types=1);

namespace App\Modules\Academic\FacultyWorkforce\Http\Web;

use App\Actions\Lecture\CreateLectureAction;
use App\Actions\Lecture\GetLectureTeachingDetailsAction;
use App\Actions\Lecture\GetTeachingHoursAction;
use App\Actions\Lecture\UpdateLectureAction;
use App\Constants\LectureRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lecture\LectureStatisticsRequest;
use App\Http\Requests\Lecture\ListAvailableLecturesRequest;
use App\Http\Requests\Lecture\ListLecturesRequest;
use App\Http\Requests\Lecture\SearchLecturesRequest;
use App\Http\Requests\Lecture\StoreLectureRequest;
use App\Http\Requests\Lecture\UpdateLectureRequest;
use App\Http\Requests\Lecture\ViewLectureTeachingDetailsRequest;
use App\Http\Requests\Lecture\ViewTeachingHoursRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Lecture;
use App\Modules\Academic\Catalog\Queries\GetSemesterFilterOptionsQuery;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingUnitTypesQuery;
use App\Queries\Lecture\ListLecturesQuery;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class LectureController extends Controller
{
    /**
     * Display a listing of lectures
     */
    public function index(
        ListLecturesRequest $request,
        ListLecturesQuery $query,
        GetSemesterFilterOptionsQuery $semesterFilterOptions,
        GetCourseOfferingUnitTypesQuery $unitTypes,
    ): Response {
        $currentCampusId = (int) session('current_campus_id');
        $validated = $request->validated();
        $semesterOptions = $semesterFilterOptions->handle();

        $filters = [
            'search' => $validated['search'] ?? '',
            'campus_id' => $validated['campus_id'] ?? 'all',
            'semester_id' => $this->resolveLectureSemesterFilter($validated['semester_id'] ?? null, $semesterOptions['active_semester_id']),
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

        return Inertia::render('Lectures/Index', [
            'lectures' => $lectures,
            'filters' => $filters,
            'semesters' => $semesterOptions['semesters'],
            'unitTypeOptions' => collect($unitTypes->handle($currentCampusId))
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => $this->getUnitTypeLabel($type),
                ])
                ->values()
                ->all(),
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

    private function resolveLectureSemesterFilter(null|string|int $semesterId, ?int $activeSemesterId): string
    {
        if ($semesterId !== null && $semesterId !== '') {
            return (string) $semesterId;
        }

        return $activeSemesterId !== null ? (string) $activeSemesterId : 'all';
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
    public function create(CampusReferenceReader $campuses): Response
    {
        $currentCampusId = session('current_campus_id');

        return Inertia::render('Lectures/Create', [
            'campuses' => collect($campuses->all())
                ->map(static fn ($campus): array => ['id' => $campus->id, 'name' => $campus->name])
                ->values()
                ->all(),
            'currentCampusId' => $currentCampusId,
        ]);
    }

    /**
     * Store a newly created lecture
     */
    public function store(StoreLectureRequest $request, CreateLectureAction $action): RedirectResponse
    {
        $action->execute($request->validated());

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

        return Inertia::render('Lectures/Show', [
            'lecture' => $lecture,
        ]);
    }

    /**
     * Show the form for editing the specified lecture
     */
    public function edit(Lecture $lecture, CampusReferenceReader $campuses): Response
    {
        return Inertia::render('Lectures/Edit', [
            'lecture' => $lecture,
            'campuses' => collect($campuses->all())
                ->map(static fn ($campus): array => ['id' => $campus->id, 'name' => $campus->name])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Update the specified lecture
     */
    public function update(UpdateLectureRequest $request, Lecture $lecture, UpdateLectureAction $action): RedirectResponse
    {
        $action->execute($lecture, $request->validated());

        return Redirect::route(LectureRoutes::INDEX)
            ->with('success', 'Lecturer updated successfully.');
    }

    /**
     * Remove the specified lecture
     */
    public function destroy(Lecture $lecture): RedirectResponse
    {
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
    public function apiSearch(SearchLecturesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = Lecture::with(['campus'])
            ->active();

        if (isset($validated['query'])) {
            $search = $validated['query'];
            $query->where(function ($q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($validated['campus_id'])) {
            $query->where('campus_id', $validated['campus_id']);
        }

        if ($request->boolean('available_only')) {
            $query->availableForAssignment();
        }

        $lectures = $query->orderByName()
            ->limit($validated['limit'] ?? 20)
            ->get();

        return ApiResponse::success($lectures, message: 'Lectures retrieved successfully');
    }

    /**
     * API endpoint for getting lectures (for dropdowns, quick edits, etc.)
     */
    public function apiIndex(ListAvailableLecturesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $isActive = array_key_exists('is_active', $validated) ? $request->boolean('is_active') : null;
        $isAvailableForAssignment = array_key_exists('is_available_for_assignment', $validated)
            ? $request->boolean('is_available_for_assignment')
            : null;

        $query = Lecture::with(['campus'])
            ->where('campus_id', $validated['campus_id'] ?? session('current_campus_id'));

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

        if ($isActive === null) {
            $query->where('is_active', true);
        }

        $lectures = $query->orderByName()
            ->limit($validated['limit'] ?? 50)
            ->get();

        return ApiResponse::success($lectures, message: 'Lecturers retrieved successfully');
    }

    /**
     * Get lecture statistics
     */
    public function statistics(LectureStatisticsRequest $request): JsonResponse
    {
        $campusId = $request->validated('campus_id');

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

        return ApiResponse::success($stats);
    }

    /**
     * Display teaching hours report for lecturers
     */
    public function teachingHours(
        ViewTeachingHoursRequest $request,
        GetTeachingHoursAction $action,
        GetSemesterFilterOptionsQuery $semesterFilterOptions,
    ): Response {
        $currentCampusId = session('current_campus_id');

        $validated = $request->validated();
        $filters = $action->normalizeFilters($validated);

        $results = $action->execute($filters, (int) $currentCampusId);

        $semesterOptions = $semesterFilterOptions->handle();
        $semesters = collect($semesterOptions['semesters'])
            ->map(fn ($semester): array => [
                'id' => $semester->id,
                'name' => $semester->name,
                'code' => $semester->code,
                'is_active' => $semester->id === $semesterOptions['active_semester_id'],
            ]);

        return Inertia::render('Lectures/TeachingHours', [
            'lecturers' => $results,
            'filters' => $filters,
            'semesters' => $semesters,
        ]);
    }

    /**
     * Display detailed teaching hours for a specific lecturer.
     */
    public function showTeachingHours(Lecture $lecture, ViewLectureTeachingDetailsRequest $request, GetLectureTeachingDetailsAction $action): Response
    {
        $validated = $request->validated();

        if (! isset($validated['date_from'])) {
            $validated['date_from'] = now()->subMonth()->day(16)->format('Y-m-d');
        }
        if (! isset($validated['date_to'])) {
            $validated['date_to'] = now()->day(15)->format('Y-m-d');
        }

        $results = $action->execute($lecture->id, $validated);

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

        return Inertia::render('Lectures/TeachingHoursDetail', [
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
