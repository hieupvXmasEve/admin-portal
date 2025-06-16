<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CourseRegistration;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\Semester;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseRegistrationController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService
    ) {
        $this->middleware('can:view_course')->only(['index', 'show']);
        $this->middleware('can:create_course')->only(['create', 'store']);
        $this->middleware('can:edit_course')->only(['edit', 'update']);
        $this->middleware('can:delete_course')->only(['destroy', 'bulkDestroy']);
    }

    /**
     * Display a listing of course registrations
     */
    public function index(Request $request): Response
    {
        $query = CourseRegistration::with([
            'student',
            'courseOffering.unit',
            'courseOffering.semester',
        ])->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('courseOffering', function ($q) use ($search) {
                $q->whereHas('unit', function ($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('semester_id') && $request->semester_id !== 'all') {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('registration_status', $request->status);
        }

        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        $registrations = $query->paginate(15)->withQueryString();

        // Get filter options
        $semesters = Semester::orderBy('start_date', 'desc')->get(['id', 'name', 'code']);

        return Inertia::render('course-registrations/Index', [
            'registrations' => $registrations,
            'filters' => $request->only(['search', 'semester_id', 'status', 'payment_status']),
            'semesters' => $semesters,
            'statusOptions' => [
                ['value' => 'registered', 'label' => 'Registered'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'dropped', 'label' => 'Dropped'],
                ['value' => 'withdrawn', 'label' => 'Withdrawn'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
            'paymentStatusOptions' => [
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'overdue', 'label' => 'Overdue'],
                ['value' => 'waived', 'label' => 'Waived'],
            ],
        ]);
    }

    /**
     * Show the form for creating a new course registration
     */
    public function create(Request $request): Response
    {
        $semesters = Semester::where('is_active', true)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code', 'start_date', 'end_date']);

        $selectedSemester = null;
        $courseOfferings = collect();

        if ($request->filled('semester_id')) {
            $selectedSemester = Semester::find($request->semester_id);

            $courseOfferings = CourseOffering::with(['unit', 'instructor'])
                ->where('semester_id', $request->semester_id)
                ->where('is_active', true)
                ->join('units', 'course_offerings.unit_id', '=', 'units.id')
                ->orderBy('units.code')
                ->orderBy('course_offerings.section_code')
                ->select('course_offerings.*')
                ->get();
        }

        return Inertia::render('course-registrations/Create', [
            'semesters' => $semesters,
            'selectedSemester' => $selectedSemester,
            'courseOfferings' => $courseOfferings,
        ]);
    }

    /**
     * Store a newly created course registration
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'course_offering_id' => 'required|exists:course_offerings,id',
            'payment_status' => 'nullable|in:pending,paid,overdue,waived',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $student = Student::findOrFail($request->student_id);
                $courseOffering = CourseOffering::findOrFail($request->course_offering_id);

                // Validate registration eligibility
                $this->validateAdminRegistration($student, $courseOffering);

                // Create registration
                CourseRegistration::create([
                    'student_id' => $student->id,
                    'course_offering_id' => $courseOffering->id,
                    'semester_id' => $courseOffering->semester_id,
                    'registration_status' => 'registered',
                    'registration_date' => now(),
                    'registration_method' => 'admin_override',
                    'credit_hours' => $courseOffering->credit_hours,
                    'tuition_amount' => $courseOffering->getTotalTuition(),
                    'fees_amount' => $courseOffering->additional_fees ?? 0,
                    'payment_status' => $request->payment_status ?? 'pending',
                    'notes' => $request->notes,
                ]);

                // Update course offering enrollment
                $courseOffering->incrementEnrollment();
                $courseOffering->updateStatus();
            });

            return Redirect::route('course-registrations.index')
                ->with('success', 'Student registered for course successfully.');
        } catch (\Exception $e) {
            return Redirect::back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified course registration
     */
    public function show(CourseRegistration $adminCourseRegistration): Response
    {
        $adminCourseRegistration->load([
            'student',
            'courseOffering.unit',
            'courseOffering.semester',
            'courseOffering.instructor',
        ]);

        return Inertia::render('course-registrations/Show', [
            'registration' => $adminCourseRegistration,
        ]);
    }

    /**
     * Show the form for editing the specified course registration
     */
    public function edit(CourseRegistration $adminCourseRegistration): Response
    {
        $adminCourseRegistration->load([
            'student',
            'courseOffering.unit',
            'courseOffering.semester',
            'courseOffering.instructor',
        ]);

        return Inertia::render('course-registrations/Edit', [
            'registration' => $adminCourseRegistration,
        ]);
    }

    /**
     * Update the specified course registration
     */
    public function update(Request $request, CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        $request->validate([
            'payment_status' => 'nullable|in:pending,paid,overdue,waived',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $adminCourseRegistration->update([
                'payment_status' => $request->payment_status ?? $adminCourseRegistration->payment_status,
                'notes' => $request->notes,
            ]);

            return Redirect::route('course-registrations.show', $adminCourseRegistration)
                ->with('success', 'Course registration updated successfully.');
        } catch (\Exception $e) {
            return Redirect::back()
                ->withInput()
                ->with('error', 'Failed to update course registration: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified course registration
     */
    public function destroy(CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        try {
            DB::transaction(function () use ($adminCourseRegistration) {
                // Update course offering enrollment
                $adminCourseRegistration->courseOffering->decrementEnrollment();
                $adminCourseRegistration->courseOffering->updateStatus();

                // Delete registration
                $adminCourseRegistration->delete();
            });

            return Redirect::route('course-registrations.index')
                ->with('success', 'Course registration deleted successfully.');
        } catch (\Exception $e) {
            return Redirect::back()
                ->with('error', 'Failed to delete course registration: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete course registrations
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:course_registrations,id',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $registrations = CourseRegistration::with('courseOffering')
                    ->whereIn('id', $request->ids)
                    ->get();

                foreach ($registrations as $registration) {
                    // Update course offering enrollment
                    $registration->courseOffering->decrementEnrollment();
                    $registration->courseOffering->updateStatus();

                    // Delete registration
                    $registration->delete();
                }
            });

            return Redirect::route('course-registrations.index')
                ->with('success', 'Selected course registrations deleted successfully.');
        } catch (\Exception $e) {
            return Redirect::back()
                ->with('error', 'Failed to delete course registrations: ' . $e->getMessage());
        }
    }

    /**
     * Drop a student from a course
     */
    public function drop(CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        try {
            $result = $this->registrationService->dropCourse($adminCourseRegistration);

            if ($result) {
                return Redirect::back()
                    ->with('success', 'Student dropped from course successfully.');
            } else {
                return Redirect::back()
                    ->with('error', 'Failed to drop student from course.');
            }
        } catch (\Exception $e) {
            return Redirect::back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Withdraw a student from a course
     */
    public function withdraw(CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        try {
            $result = $this->registrationService->withdrawFromCourse($adminCourseRegistration);

            if ($result) {
                return Redirect::back()
                    ->with('success', 'Student withdrawn from course successfully.');
            } else {
                return Redirect::back()
                    ->with('error', 'Failed to withdraw student from course.');
            }
        } catch (\Exception $e) {
            return Redirect::back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get available courses for student registration
     */
    public function getAvailableCourses(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $student = Student::findOrFail($request->student_id);
            $availableCourses = $this->registrationService->getAvailableCoursesForStudent(
                $student,
                $request->semester_id
            );

            return response()->json([
                'success' => true,
                'data' => $availableCourses,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check registration eligibility
     */
    public function checkEligibility(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'course_offering_id' => 'required|exists:course_offerings,id',
        ]);

        try {
            $student = Student::findOrFail($request->student_id);
            $courseOffering = CourseOffering::findOrFail($request->course_offering_id);

            $eligibility = $this->checkStudentEligibility($student, $courseOffering);

            return response()->json([
                'success' => true,
                'data' => $eligibility,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get student's current registrations
     */
    public function getStudentRegistrations(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'semester_id' => 'nullable|exists:semesters,id',
        ]);

        $query = CourseRegistration::with(['courseOffering.unit'])
            ->where('student_id', $request->student_id);

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        $registrations = $query->get();

        return response()->json([
            'success' => true,
            'data' => $registrations,
        ]);
    }

    /**
     * Validate admin registration
     */
    private function validateAdminRegistration(Student $student, CourseOffering $courseOffering): void
    {
        // Check if student is active
        if ($student->status !== 'active') {
            throw new \Exception('Student is not active.');
        }

        // Check if already registered
        $existingRegistration = CourseRegistration::where('student_id', $student->id)
            ->where('course_offering_id', $courseOffering->id)
            ->where('semester_id', $courseOffering->semester_id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->exists();

        if ($existingRegistration) {
            throw new \Exception('Student is already registered for this course.');
        }

        // Check course capacity (admin can override)
        if ($courseOffering->current_enrollment >= $courseOffering->max_enrollment) {
            // Admin override - log this action but allow registration
            Log::info('Admin override registration', [
                'student_id' => $student->id,
                'course_offering_id' => $courseOffering->id,
                'current_enrollment' => $courseOffering->current_enrollment,
                'max_enrollment' => $courseOffering->max_enrollment,
            ]);
        }
    }

    /**
     * Check student eligibility for course
     */
    private function checkStudentEligibility(Student $student, CourseOffering $courseOffering): array
    {
        $eligible = true;
        $reasons = [];

        // Check if student is active
        if ($student->status !== 'active') {
            $eligible = false;
            $reasons[] = 'Student is not active';
        }

        // Check if already registered
        $existingRegistration = CourseRegistration::where('student_id', $student->id)
            ->where('course_offering_id', $courseOffering->id)
            ->where('semester_id', $courseOffering->semester_id)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->exists();

        if ($existingRegistration) {
            $eligible = false;
            $reasons[] = 'Already registered for this course';
        }

        // Check course availability
        if (!$courseOffering->isAvailableForRegistration()) {
            $eligible = false;
            $reasons[] = 'Course is not available for registration';
        }

        // Check course capacity
        if ($courseOffering->current_enrollment >= $courseOffering->max_enrollment) {
            $reasons[] = 'Course is at full capacity (admin can override)';
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'can_override' => true, // Admin can always override
        ];
    }
}
