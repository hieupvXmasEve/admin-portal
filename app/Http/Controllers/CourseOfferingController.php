<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseOfferingRequest;
use App\Http\Requests\UpdateCourseOfferingRequest;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class CourseOfferingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view_course')->only(['index', 'show']);
        $this->middleware('can:create_course')->only(['create', 'store']);
        $this->middleware('can:edit_course')->only(['edit', 'update']);
        $this->middleware('can:delete_course')->only(['destroy', 'bulkDelete']);
    }

    /**
     * Display a listing of course offerings
     */
    public function index(Request $request): Response
    {
        $query = CourseOffering::with(['semester', 'unit', 'instructor'])
            ->orderBy('semester_id', 'desc')
            ->orderBy('section_code');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('section_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('unit', function ($unitQuery) use ($search) {
                        $unitQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('semester_id') && $request->semester_id !== 'all') {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('enrollment_status') && $request->enrollment_status !== 'all') {
            $query->where('enrollment_status', $request->enrollment_status);
        }

        if ($request->filled('delivery_mode') && $request->delivery_mode !== 'all') {
            $query->where('delivery_mode', $request->delivery_mode);
        }

        $courseOfferings = $query->paginate(15)->withQueryString();

        // Get filter options
        $semesters = Semester::orderBy('start_date', 'desc')->get(['id', 'name', 'code']);

        return Inertia::render('course-offerings/Index', [
            'courseOfferings' => $courseOfferings,
            'filters' => $request->only(['search', 'semester_id', 'enrollment_status', 'delivery_mode']),
            'semesters' => $semesters,
            'enrollmentStatusOptions' => [
                ['value' => 'open', 'label' => 'Open'],
                ['value' => 'closed', 'label' => 'Closed'],
                ['value' => 'waitlist_only', 'label' => 'Waitlist Only'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'deliveryModeOptions' => [
                ['value' => 'in_person', 'label' => 'In Person'],
                ['value' => 'online', 'label' => 'Online'],
                ['value' => 'hybrid', 'label' => 'Hybrid'],
                ['value' => 'blended', 'label' => 'Blended'],
            ],
        ]);
    }

    /**
     * Show the form for creating a new course offering
     */
    public function create(): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')->get(['id', 'name', 'code', 'start_date', 'end_date']);
        $units = Unit::orderBy('code')->get(['id', 'code', 'name', 'credit_points']);
        $campuses = Campus::orderBy('name')->get(['id', 'name', 'code']);

        // Get users who have instructor role in any campus
        $instructors = User::whereHas('campusRoles', function ($query) {
            $query->whereHas('role', function ($roleQuery) {
                $roleQuery->where('code', 'instructor')
                    ->orWhere('name', 'instructor')
                    ->orWhere('name', 'Instructor');
            });
        })->orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('course-offerings/Create', [
            'semesters' => $semesters,
            'units' => $units,
            'campuses' => $campuses,
            'instructors' => $instructors,
        ]);
    }

    /**
     * Store a newly created course offering
     */
    public function store(StoreCourseOfferingRequest $request): RedirectResponse
    {
        $courseOffering = CourseOffering::create($request->validated());

        return Redirect::route('course-offerings.index')
            ->with('success', 'Course offering created successfully.');
    }

    /**
     * Display the specified course offering
     */
    public function show(CourseOffering $courseOffering): Response
    {
        $courseOffering->load([
            'semester',
            'unit',
            'instructor',
            'courseRegistrations.student'
        ]);

        return Inertia::render('course-offerings/Show', [
            'courseOffering' => $courseOffering,
        ]);
    }

    /**
     * Show the form for editing the specified course offering
     */
    public function edit(CourseOffering $courseOffering): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')->get(['id', 'name', 'code', 'start_date', 'end_date']);
        $units = Unit::orderBy('code')->get(['id', 'code', 'name', 'credit_points']);

        // Get users who have instructor role in any campus
        $instructors = User::whereHas('campusRoles', function ($query) {
            $query->whereHas('role', function ($roleQuery) {
                $roleQuery->where('code', 'instructor')
                    ->orWhere('name', 'instructor')
                    ->orWhere('name', 'Instructor');
            });
        })->orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('course-offerings/Edit', [
            'courseOffering' => $courseOffering,
            'semesters' => $semesters,
            'units' => $units,
            'instructors' => $instructors,
        ]);
    }

    /**
     * Update the specified course offering
     */
    public function update(UpdateCourseOfferingRequest $request, CourseOffering $courseOffering): RedirectResponse
    {
        $courseOffering->update($request->validated());

        return Redirect::route('course-offerings.index')
            ->with('success', 'Course offering updated successfully.');
    }

    /**
     * Remove the specified course offering
     */
    public function destroy(CourseOffering $courseOffering): RedirectResponse
    {
        // Check if there are any registrations
        if ($courseOffering->courseRegistrations()->count() > 0) {
            return Redirect::back()
                ->with('error', 'Cannot delete course offering with existing registrations.');
        }

        $courseOffering->delete();

        return Redirect::route('course-offerings.index')
            ->with('success', 'Course offering deleted successfully.');
    }

    /**
     * Bulk delete course offerings
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:course_offerings,id',
        ]);

        $courseOfferings = CourseOffering::whereIn('id', $request->ids)->get();

        foreach ($courseOfferings as $courseOffering) {
            if ($courseOffering->courseRegistrations()->count() > 0) {
                return Redirect::back()
                    ->with('error', 'Cannot delete course offerings with existing registrations.');
            }
        }

        CourseOffering::whereIn('id', $request->ids)->delete();

        return Redirect::route('course-offerings.index')
            ->with('success', 'Selected course offerings deleted successfully.');
    }

    /**
     * Toggle course offering status
     */
    public function toggleStatus(CourseOffering $courseOffering): RedirectResponse
    {
        $newStatus = $courseOffering->enrollment_status === 'open' ? 'closed' : 'open';
        $courseOffering->update(['enrollment_status' => $newStatus]);

        return Redirect::back()
            ->with('success', "Course offering status updated to {$newStatus}.");
    }

    /**
     * Get course offering statistics
     */
    public function statistics(Request $request)
    {
        $semesterId = $request->semester_id;

        $query = CourseOffering::query();

        if ($semesterId && $semesterId !== 'all') {
            $query->where('semester_id', $semesterId);
        }

        $stats = [
            'total_offerings' => $query->count(),
            'active_offerings' => (clone $query)->where('is_active', true)->where('enrollment_status', 'open')->count(),
            'full_offerings' => (clone $query)->whereRaw('current_enrollment >= max_capacity')->count(),
            'cancelled_offerings' => (clone $query)->where('enrollment_status', 'cancelled')->count(),
            'total_enrollment' => (clone $query)->sum('current_enrollment'),
            'total_capacity' => (clone $query)->sum('max_capacity'),
        ];

        $stats['enrollment_rate'] = $stats['total_capacity'] > 0
            ? round(($stats['total_enrollment'] / $stats['total_capacity']) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
