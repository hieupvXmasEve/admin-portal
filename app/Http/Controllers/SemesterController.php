<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Semester;
use App\Services\SemesterManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class SemesterController extends Controller
{
    public function __construct(
        private SemesterManagementService $semesterService
    )
    {
    }

    public function index(Request $request): Response
    {
        // Validate input
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'filter.name' => 'string|max:255',
            'filter.year' => 'string|max:4',
            'filter.semester_type' => 'string|in:fall,spring,summer,winter,intersession',
            'filter.locked_status' => 'string|in:locked,unlocked',
            'filter.is_current' => 'boolean',
            'filter.is_registration_open' => 'boolean',
        ]);

        $page = $validated['page'] ?? 1;
        $per_page = $validated['per_page'] ?? 10;
        $currentCampusId = session('current_campus_id');

        $query = Semester::with('campus')
            ->where('campus_id', $currentCampusId)
            ->orderBy('start_date', 'desc');

        // Global search
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('academic_year', 'like', "%{$search}%")
                    ->orWhereRaw('YEAR(start_date) = ?', [$search])
                    ->orWhereRaw('YEAR(end_date) = ?', [$search]);
            });
        }

        // Column filters
        if (!empty($validated['filter'])) {
            foreach ($validated['filter'] as $column => $value) {
                if ($value === null || $value === '') continue;

                switch ($column) {
                    case 'name':
                        $query->where('name', 'like', "%{$value}%");
                        break;
                    case 'year':
                        $query->where(function ($q) use ($value) {
                            $q->whereRaw('YEAR(start_date) = ?', [$value])
                                ->orWhereRaw('YEAR(end_date) = ?', [$value])
                                ->orWhere('academic_year', 'like', "%{$value}%");
                        });
                        break;
                    case 'semester_type':
                        $query->where('semester_type', $value);
                        break;
                    case 'locked_status':
                        $query->where('locked_status', $value);
                        break;
                    case 'is_current':
                        $query->where('is_current', $value);
                        break;
                    case 'is_registration_open':
                        $query->where('is_registration_open', $value);
                        break;
                }
            }
        }

        $semesters = $query->paginate($per_page, ['*'], 'page', $page)
            ->withQueryString();

        // Add statistics for each semester
        $semesters->getCollection()->transform(function ($semester) {
            $semester->statistics = $this->semesterService->getSemesterStatistics($semester);
            $semester->enrollment_status = $semester->getEnrollmentStatus();
            return $semester;
        });

        return Inertia::render('semesters/Index', [
            'semesters' => Inertia::deepMerge($semesters),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'name' => $validated['filter']['name'] ?? null,
                'year' => $validated['filter']['year'] ?? null,
                'semester_type' => $validated['filter']['semester_type'] ?? null,
                'locked_status' => $validated['filter']['locked_status'] ?? null,
                'is_current' => $validated['filter']['is_current'] ?? null,
                'is_registration_open' => $validated['filter']['is_registration_open'] ?? null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('semesters/Add');
    }

    public function store(Request $request): RedirectResponse
    {
        Log::info('Store semester request data:', $request->all());

        $validated = $request->validate([
            'name' => 'required|string|max:25',
            'semester_type' => 'required|in:fall,spring,summer,winter,intersession',
            'academic_year' => 'nullable|string|max:9',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'enrollment_start_date' => 'nullable|date|before:start_date',
            'enrollment_end_date' => 'nullable|date|after:enrollment_start_date|before:start_date',
            'add_drop_deadline' => 'nullable|date|after:start_date|before:end_date',
            'withdrawal_deadline' => 'nullable|date|after:add_drop_deadline|before:end_date',
            'final_exam_start' => 'nullable|date|after:start_date|before:end_date',
            'final_exam_end' => 'nullable|date|after:final_exam_start|before_or_equal:end_date',
            'locked_status' => 'required|in:locked,unlocked',
            'is_current' => 'boolean',
            'is_registration_open' => 'boolean',
            'max_credit_load' => 'nullable|numeric|min:1|max:30',
            'min_credit_load' => 'nullable|numeric|min:1|max:30',
            'is_attendance_locked' => 'boolean',
            'is_certificate_locked' => 'boolean',
            'has_tuition_fee' => 'boolean',
            'has_gc_fee' => 'boolean',
        ]);

        $currentCampusId = session('current_campus_id');

        if (!$currentCampusId) {
            return redirect()->back()->withErrors(['error' => 'No campus selected.']);
        }

        $campus = Campus::findOrFail($currentCampusId);

        // Check for duplicate name within the same campus
        $exists = Semester::where('campus_id', $currentCampusId)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['name' => 'A semester with this name already exists for this campus.']);
        }

        try {
            $semester = $this->semesterService->createSemester($campus, $validated);

            // Set as current semester if requested
            if ($validated['is_current'] ?? false) {
                $this->semesterService->setCurrentSemester($semester);
            }

            return redirect()->route('semester.index')->with('success', 'Semester created successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating semester: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(Semester $semester): Response
    {
        // Check if semester belongs to current campus
        $currentCampusId = session('current_campus_id');
        if ($semester->campus_id !== $currentCampusId) {
            abort(403);
        }

        $semester->load(['campus']);
        $statistics = $this->semesterService->getSemesterStatistics($semester);
        $events = $this->semesterService->getSemesterEvents($semester);

        return Inertia::render('semesters/Show', [
            'semester' => $semester,
            'statistics' => $statistics,
            'events' => $events,
            'enrollment_status' => $semester->getEnrollmentStatus(),
        ]);
    }

    public function edit(Semester $semester): Response
    {
        // Check if semester belongs to current campus
        $currentCampusId = session('current_campus_id');
        if ($semester->campus_id !== $currentCampusId) {
            abort(403);
        }

        return Inertia::render('semesters/Edit', [
            'semester' => $semester,
        ]);
    }

    public function update(Request $request, Semester $semester): RedirectResponse
    {
        Log::info('Update semester request data:', $request->all());

        // Check if semester belongs to current campus
        $currentCampusId = session('current_campus_id');
        if ($semester->campus_id !== $currentCampusId) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:25',
            'semester_type' => 'required|in:fall,spring,summer,winter,intersession',
            'academic_year' => 'nullable|string|max:9',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'enrollment_start_date' => 'nullable|date|before:start_date',
            'enrollment_end_date' => 'nullable|date|after:enrollment_start_date|before:start_date',
            'add_drop_deadline' => 'nullable|date|after:start_date|before:end_date',
            'withdrawal_deadline' => 'nullable|date|after:add_drop_deadline|before:end_date',
            'final_exam_start' => 'nullable|date|after:start_date|before:end_date',
            'final_exam_end' => 'nullable|date|after:final_exam_start|before_or_equal:end_date',
            'locked_status' => 'required|in:locked,unlocked',
            'is_current' => 'boolean',
            'is_registration_open' => 'boolean',
            'max_credit_load' => 'nullable|numeric|min:1|max:30',
            'min_credit_load' => 'nullable|numeric|min:1|max:30',
            'is_attendance_locked' => 'boolean',
            'is_certificate_locked' => 'boolean',
            'has_tuition_fee' => 'boolean',
            'has_gc_fee' => 'boolean',
        ]);

        // Check for duplicate name within the same campus (excluding current semester)
        $exists = Semester::where('campus_id', $currentCampusId)
            ->where('name', $validated['name'])
            ->where('id', '!=', $semester->id)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['name' => 'A semester with this name already exists for this campus.']);
        }

        try {
            $semester->update($validated);

            // Set as current semester if requested
            if ($validated['is_current'] ?? false) {
                $this->semesterService->setCurrentSemester($semester);
            }

            return redirect()->route('semester.index')->with('success', 'Semester updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating semester: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Semester $semester): RedirectResponse
    {
        // Check if semester belongs to current campus
        $currentCampusId = session('current_campus_id');
        if ($semester->campus_id !== $currentCampusId) {
            abort(403);
        }

        // Check if semester is locked
        if ($semester->isLocked()) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete a locked semester.']);
        }

        // Check if semester has enrollments
        if ($semester->enrollments()->exists()) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete a semester with existing enrollments.']);
        }

        $semester->delete();

        return redirect()->route('semester.index')->with('success', 'Semester deleted successfully!');
    }

    /**
     * Set semester as current
     */
    public function setCurrent(Semester $semester): JsonResponse
    {
        $currentCampusId = session('current_campus_id');
        if ($semester->campus_id !== $currentCampusId) {
            abort(403);
        }

        try {
            $this->semesterService->setCurrentSemester($semester);
            return response()->json(['message' => 'Semester set as current successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Open enrollment for semester
     */
    public function openEnrollment(Semester $semester): JsonResponse
    {
        $currentCampusId = session('current_campus_id');
        if ($semester->campus_id !== $currentCampusId) {
            abort(403);
        }

        try {
            $this->semesterService->openEnrollment($semester);
            return response()->json(['message' => 'Enrollment opened successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Close enrollment for semester
     */
    public function closeEnrollment(Semester $semester): JsonResponse
    {
        $currentCampusId = session('current_campus_id');
        if ($semester->campus_id !== $currentCampusId) {
            abort(403);
        }

        try {
            $this->semesterService->closeEnrollment($semester);
            return response()->json(['message' => 'Enrollment closed successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get academic calendar for current campus
     */
    public function calendar(Request $request): Response
    {
        $validated = $request->validate([
            'academic_year' => 'nullable|string|max:9',
        ]);

        $currentCampusId = session('current_campus_id');
        $campus = Campus::findOrFail($currentCampusId);

        $calendar = $this->semesterService->getAcademicCalendar(
            $campus,
            $validated['academic_year'] ?? null
        );

        return Inertia::render('semesters/Calendar', [
            'calendar' => $calendar,
            'campus' => $campus,
            'academic_year' => $validated['academic_year'] ?? null,
        ]);
    }

    /**
     * Copy offerings from previous semester
     */
    public function copyOfferings(Request $request, Semester $targetSemester): JsonResponse
    {
        $validated = $request->validate([
            'source_semester_id' => 'required|exists:semesters,id',
        ]);

        $currentCampusId = session('current_campus_id');
        if ($targetSemester->campus_id !== $currentCampusId) {
            abort(403);
        }

        $sourceSemester = Semester::findOrFail($validated['source_semester_id']);
        if ($sourceSemester->campus_id !== $currentCampusId) {
            abort(403);
        }

        try {
            $copiedOfferings = $this->semesterService->copyOfferingsFromPreviousSemester(
                $targetSemester,
                $sourceSemester
            );

            return response()->json([
                'message' => "Successfully copied {$copiedOfferings->count()} offerings",
                'count' => $copiedOfferings->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
