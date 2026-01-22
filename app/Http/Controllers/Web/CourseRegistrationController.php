<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\CourseRegistrationRoutes;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class CourseRegistrationController extends Controller
{
    public function __construct(
        private RegistrationService $registrationService
    ) {
        $this->middleware('can:view_course_registration')->only(['index', 'show']);
        $this->middleware('can:create_course_registration')->only(['create', 'store']);
        $this->middleware('can:edit_course_registration')->only(['edit', 'update']);
        $this->middleware('can:delete_course_registration')->only(['destroy', 'bulkDestroy']);
        $this->middleware('can:manage_course_registration')->only(['drop', 'withdraw']);
    }

    /**
     * Display a listing of course registrations
     */
    public function index(Request $request): Response
    {
        $query = CourseRegistration::with([
            'student',
            'semester',
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
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('semester_id') && $request->semester_id !== 'all') {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('registration_status', $request->status);
        }

        if ($request->filled('course_offering_id') && $request->course_offering_id !== 'all') {
            $query->where('course_offering_id', $request->course_offering_id);
        }

        $perPage = $request->get('per_page', 15);
        $registrations = $query->paginate($perPage)->withQueryString();

        // Calculate statistics across all records (not just current page)
        $statsQuery = CourseRegistration::query();

        // Apply same filters to stats query (except pagination)
        if ($request->filled('search')) {
            $search = $request->search;
            $statsQuery->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('courseOffering', function ($q) use ($search) {
                $q->whereHas('unit', function ($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('semester_id') && $request->semester_id !== 'all') {
            $statsQuery->where('semester_id', $request->semester_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $statsQuery->where('registration_status', $request->status);
        }

        if ($request->filled('course_offering_id') && $request->course_offering_id !== 'all') {
            $statsQuery->where('course_offering_id', $request->course_offering_id);
        }

        $statistics = [
            'total_registrations' => $statsQuery->count(),
            'active_registrations' => (clone $statsQuery)->whereIn('registration_status', ['registered', 'confirmed'])->count(),
            'pending_registrations' => (clone $statsQuery)->where('registration_status', 'registered')->count(),
        ];

        // Get filter options
        $semesters = Semester::orderBy('start_date', 'desc')->get(['id', 'name', 'code']);

        // Get course offerings for the selected semester
        $courseOfferings = collect();
        if ($request->filled('semester_id') && $request->semester_id !== 'all') {
            $courseOfferings = CourseOffering::with(['unit'])
                ->where('course_offerings.semester_id', $request->semester_id)
                ->where('course_offerings.is_active', true)
                ->join('units', 'course_offerings.unit_id', '=', 'units.id')
                ->orderBy('units.code')
                ->orderBy('course_offerings.section_code')
                ->select('course_offerings.*')
                ->get()
                ->map(fn($offering) => [
                    'value' => $offering->id,
                    'label' => "{$offering->course_code} - {$offering->course_title}" .
                        ($offering->section_code ? " (Section {$offering->section_code})" : ''),
                ]);
        }

        return Inertia::render('course-registrations/Index', [
            'registrations' => $registrations,
            'statistics' => $statistics,
            'filters' => $request->only(['search', 'semester_id', 'status', 'course_offering_id', 'per_page']),
            'semesters' => $semesters,
            'courseOfferings' => $courseOfferings,
            'statusOptions' => [
                ['value' => 'registered', 'label' => 'Registered'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'dropped', 'label' => 'Dropped'],
                ['value' => 'withdrawn', 'label' => 'Withdrawn'],
                ['value' => 'completed', 'label' => 'Completed'],
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

            $courseOfferings = CourseOffering::with(['unit', 'lecture'])
                ->where('course_offerings.semester_id', $request->semester_id)
                ->where('course_offerings.is_active', true)
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
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $student = Student::findOrFail($request->student_id);

            // Get the active semester
            $activeSemester = Semester::where('is_active', true)->first();
            if (! $activeSemester) {
                throw new \Exception('No active semester found');
            }

            $registeredCount = 0;
            $errors = [];

            DB::transaction(function () use ($request, $student, $activeSemester, &$registeredCount, &$errors) {
                foreach ($request->unit_ids as $unitId) {
                    try {
                        // Find the best course offering for this unit in the active semester
                        $courseOffering = CourseOffering::with(['unit'])
                            ->whereHas('unit', function ($query) use ($unitId) {
                                $query->where('unit_id', $unitId);
                            })
                            ->where('course_offerings.semester_id', $activeSemester->id)
                            ->where('course_offerings.is_active', true)
                            ->where('course_offerings.enrollment_status', 'open')
                            ->orderBy('course_offerings.current_enrollment')
                            ->first();

                        if (! $courseOffering) {
                            $errors[] = "No available course offering found for unit ID {$unitId}";

                            continue;
                        }

                        // Validate registration
                        $this->validateAdminRegistration($student, $courseOffering);

                        // Create registration
                        CourseRegistration::create([
                            'student_id' => $student->id,
                            'course_offering_id' => $courseOffering->id,
                            'semester_id' => $activeSemester->id,
                            'registration_date' => now(),
                            'registration_status' => 'confirmed',
                            'credit_hours' => $courseOffering->unit->credit_points,
                            'notes' => $request->notes,
                        ]);

                        // Update course offering enrollment
                        $courseOffering->incrementEnrollment();
                        $courseOffering->updateStatus();

                        $registeredCount++;
                    } catch (\Exception $e) {
                        $errors[] = "Unit ID {$unitId}: " . $e->getMessage();
                    }
                }
            });

            if ($registeredCount > 0) {
                $message = "Successfully registered student for {$registeredCount} unit(s).";
                if (! empty($errors)) {
                    $message .= ' Some units could not be registered: ' . implode('; ', $errors);
                }

                return Redirect::route(CourseRegistrationRoutes::INDEX)
                    ->with('success', $message);
            } else {
                return Redirect::back()
                    ->withInput()
                    ->with('error', 'No units were registered. Errors: ' . implode('; ', $errors));
            }
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
            'courseOffering.lecture',
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
            'courseOffering.lecture',
        ]);

        $canEditStatus = $this->canEditRegistrationStatus($adminCourseRegistration->courseOffering);

        return Inertia::render('course-registrations/Edit', [
            'registration' => $adminCourseRegistration,
            'canEditStatus' => $canEditStatus,
        ]);
    }

    /**
     * Update the specified course registration
     */
    public function update(Request $request, CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        $request->validate([
            'registration_status' => 'required|in:registered,confirmed,dropped,withdrawn,completed,defer',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Check if status can be changed (only during course offering registration period)
            $courseOffering = $adminCourseRegistration->courseOffering;
            $canEditStatus = $this->canEditRegistrationStatus($courseOffering);

            if (! $canEditStatus && $request->registration_status !== $adminCourseRegistration->registration_status) {
                return Redirect::back()
                    ->withInput()
                    ->with('error', 'Registration status can only be changed during the course registration period.');
            }

            $adminCourseRegistration->update([
                'registration_status' => $request->registration_status,
                'notes' => $request->notes,
            ]);

            return Redirect::route(CourseRegistrationRoutes::SHOW, $adminCourseRegistration)
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

            return Redirect::route(CourseRegistrationRoutes::INDEX)
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

            return Redirect::route(CourseRegistrationRoutes::INDEX)
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
     * Get available units for student registration in active semester
     */
    public function getAvailableUnits(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            $student = Student::findOrFail($request->student_id);

            // Get the active semester (only one should be active at a time)
            $activeSemester = Semester::where('is_active', true)->first();

            if (! $activeSemester) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active semester found',
                ], 400);
            }

            // Get available course offerings for the student
            $availableCourses = $this->registrationService->getAvailableCoursesForStudent(
                $student,
                $activeSemester->id
            );

            // Group by unit and check eligibility
            $unitsData = [];
            foreach ($availableCourses as $courseData) {
                $offering = $courseData['offering'];
                $unit = $offering->unit;

                if (! isset($unitsData[$unit->id])) {
                    $unitsData[$unit->id] = [
                        'unit' => $unit,
                        'offerings' => [],
                        'is_eligible' => true,
                        'is_already_registered' => false,
                        'reasons' => [],
                    ];
                }

                $unitsData[$unit->id]['offerings'][] = $offering;

                // If any offering is not eligible, mark the unit as not eligible
                if (! $courseData['eligible']) {
                    $unitsData[$unit->id]['is_eligible'] = false;
                    $unitsData[$unit->id]['reasons'] = array_merge(
                        $unitsData[$unit->id]['reasons'],
                        $courseData['reasons']
                    );
                }
            }

            // Get already registered units with full data
            $registeredUnits = [];
            $registrations = CourseRegistration::where('student_id', $student->id)
                ->where('semester_id', $activeSemester->id)
                ->whereIn('registration_status', ['registered', 'confirmed'])
                ->with('courseOffering.unit')
                ->get();

            $registeredUnitIds = [];
            foreach ($registrations as $registration) {
                $unit = $registration->courseOffering->unit;
                $registeredUnitIds[] = $unit->id;

                $registeredUnits[] = [
                    'unit' => $unit,
                    'offerings' => [$registration->courseOffering],
                    'is_eligible' => false,
                    'is_already_registered' => true,
                    'reasons' => ['Already registered for this unit'],
                ];
            }

            // Mark units as already registered in unitsData
            foreach ($registeredUnitIds as $unitId) {
                if (isset($unitsData[$unitId])) {
                    $unitsData[$unitId]['is_already_registered'] = true;
                    $unitsData[$unitId]['is_eligible'] = false;
                    $unitsData[$unitId]['reasons'][] = 'Already registered for this unit';
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'semester' => $activeSemester,
                    'units' => array_values($unitsData),
                    'registered_units' => $registeredUnits,
                ],
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
        if (! $student->isActive()) {
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
        if (! $student->isActive()) {
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
        if (! $courseOffering->isAvailableForRegistration()) {
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

    /**
     * Check if registration status can be edited (only during course offering registration period)
     */
    private function canEditRegistrationStatus(CourseOffering $courseOffering): bool
    {
        $now = now()->toDateString();

        // If no registration dates are set, allow editing
        if (! $courseOffering->registration_start_date && ! $courseOffering->registration_end_date) {
            return true;
        }

        // Check if current date is within registration period
        $startOk = ! $courseOffering->registration_start_date || $courseOffering->registration_start_date <= $now;
        $endOk = ! $courseOffering->registration_end_date || $courseOffering->registration_end_date >= $now;

        return $startOk && $endOk;
    }
}
