<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Semester;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\CourseRegistration;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SemesterEnrollmentController extends Controller
{
    /**
     * Show the semester enrollment management page
     */
    public function show(Semester $semester): Response
    {

        $semester->load(['enrollments.student.curriculumVersion', 'courseOfferings.curriculumUnit.unit']);

        $enrollmentStats = [
            'total_enrolled' => $semester->enrollments()->count(),
            'by_status' => $semester->enrollments()
                ->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'by_semester_number' => $semester->enrollments()
                ->groupBy('semester_number')
                ->selectRaw('semester_number, count(*) as count')
                ->orderBy('semester_number')
                ->pluck('count', 'semester_number')
                ->toArray(),
        ];

        return Inertia::render('semesters/Enrollment', [
            'semester' => $semester,
            'enrollmentStats' => $enrollmentStats,
        ]);
    }

    /**
     * Step 1: Generate enrollments for students
     */
    public function generateEnrollments(Semester $semester): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Get current campus ID from session
            $currentCampusId = session()->get('current_campus_id');

            if (!$currentCampusId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No campus selected. Please select a campus first.',
                ], 400);
            }

            // Get active students with valid curriculum_version_id for current campus
            $eligibleStudents = Student::where('status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->whereDoesntHave('enrollments', function ($query) use ($semester) {
                    $query->where('semester_id', $semester->id);
                })
                ->with('curriculumVersion')
                ->get();

            if ($eligibleStudents->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No eligible students found for enrollment in the selected campus',
                ], 200);
            }

            $enrollmentsCreated = 0;
            $errors = [];

            foreach ($eligibleStudents as $student) {
                try {
                    // Determine semester_number
                    $latestEnrollment = Enrollment::where('student_code', $student->id)
                        ->orderBy('semester_number', 'desc')
                        ->first();

                    $semesterNumber = $latestEnrollment ? $latestEnrollment->semester_number + 1 : 1;

                    // Validate semester number doesn't exceed reasonable limits
                    if ($semesterNumber > 8) {
                        $errors[] = "Student {$student->student_code} has exceeded maximum semester limit";
                        continue;
                    }

                    Enrollment::create([
                        'student_code' => $student->id,
                        'semester_id' => $semester->id,
                        'curriculum_version_id' => $student->curriculum_version_id,
                        'semester_number' => $semesterNumber,
                        'status' => 'in_progress',
                    ]);

                    $enrollmentsCreated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to enroll student {$student->student_code}: {$e->getMessage()}";
                    Log::error('Enrollment creation failed', [
                        'student_code' => $student->id,
                        'semester_id' => $semester->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$enrollmentsCreated} enrollments",
                'enrollments_created' => $enrollmentsCreated,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Generate enrollments failed', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate enrollments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 2: Get suggested courses to open based on enrollments
     */
    public function getSuggestedCourses(Semester $semester): JsonResponse
    {

        try {
            // Get current campus ID from session
            $currentCampusId = session()->get('current_campus_id');

            if (!$currentCampusId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No campus selected. Please select a campus first.',
                ], 400);
            }

            // Get all enrollments for this semester filtered by campus
            $enrollments = Enrollment::where('semester_id', $semester->id)
                ->where('status', 'in_progress')
                ->whereHas('student', function ($query) use ($currentCampusId) {
                    $query->where('campus_id', $currentCampusId);
                })
                ->with(['student.curriculumVersion', 'curriculumVersion'])
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No enrollments found for this semester in the selected campus. Please generate enrollments first.',
                ], 200);
            }

            // Group enrollments by curriculum_version_id and semester_number
            $enrollmentGroups = $enrollments->groupBy(function ($enrollment) {
                return $enrollment->curriculum_version_id . '_' . $enrollment->semester_number;
            });

            // Log::info('Enrollment Groups', ['enrollment_groups' => $enrollmentGroups->toArray()]);

            $unitDemand = [];

            foreach ($enrollmentGroups as $group) {
                $firstEnrollment = $group->first();
                $curriculumVersionId = $firstEnrollment->curriculum_version_id;
                $semesterNumber = $firstEnrollment->semester_number;
                $studentCount = $group->count();

                // Get curriculum units for this group
                $curriculumUnits = CurriculumUnit::where('curriculum_version_id', $curriculumVersionId)
                    ->where('semester_number', $semesterNumber)
                    ->with(['unit', 'curriculumVersion.program', 'curriculumVersion.specialization'])
                    ->get();

                foreach ($curriculumUnits as $curriculumUnit) {
                    $unitId = $curriculumUnit->unit_id;

                    if (!isset($unitDemand[$unitId])) {
                        $unitDemand[$unitId] = [
                            'unit' => $curriculumUnit->unit,
                            'estimated_students' => 0,
                            'curriculum_details' => [],
                            'existing_offerings' => 0,
                        ];
                    }

                    $unitDemand[$unitId]['estimated_students'] += $studentCount;
                    $unitDemand[$unitId]['curriculum_details'][] = [
                        'program' => $curriculumUnit->curriculumVersion->program?->name,
                        'specialization' => $curriculumUnit->curriculumVersion->specialization?->name,
                        'semester_number' => $semesterNumber,
                        'student_count' => $studentCount,
                        'is_required' => $curriculumUnit->is_compulsory ?? true,
                    ];
                }
            }

            // Get existing course offerings for this semester
            $existingOfferings = CourseOffering::where('course_offerings.semester_id', $semester->id)
                ->join('curriculum_units', 'course_offerings.curriculum_unit_id', '=', 'curriculum_units.id')
                ->groupBy('curriculum_units.unit_id')
                ->selectRaw('curriculum_units.unit_id, count(*) as offering_count')
                ->pluck('offering_count', 'curriculum_units.unit_id')
                ->toArray();

            // Update existing offerings count
            foreach ($unitDemand as $unitId => &$demand) {
                $demand['existing_offerings'] = $existingOfferings[$unitId] ?? 0;
            }

            // Sort by estimated students descending
            $sortedDemand = collect($unitDemand)->sortByDesc('estimated_students')->values();

            return response()->json([
                'success' => true,
                'message' => 'Suggested courses loaded successfully',
                'data' => [
                    'suggested_courses' => $sortedDemand,
                    'total_unique_units' => count($unitDemand),
                    'total_estimated_students' => array_sum(array_column($unitDemand, 'estimated_students')),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get suggested courses failed', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get suggested courses: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 3A: Bulk open course offerings
     */
    public function bulkOpenCourses(Request $request, Semester $semester): JsonResponse
    {
        $request->validate([
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'required|integer|exists:units,id',
            'default_capacity' => 'nullable|integer|min:1|max:10000',
            'delivery_mode' => 'nullable|in:in_person,online,hybrid,blended',
        ]);

        try {
            DB::beginTransaction();

            $unitIds = $request->unit_ids;
            $defaultCapacity = $request->default_capacity ?? 30;
            $deliveryMode = $request->delivery_mode ?? 'in_person';
            $offeringsCreated = 0;
            $errors = [];

            foreach ($unitIds as $unitId) {
                try {
                    // Find curriculum unit for this unit
                    $curriculumUnit = CurriculumUnit::where('unit_id', $unitId)->first();

                    if (!$curriculumUnit) {
                        $errors[] = "No curriculum unit found for unit ID {$unitId}";
                        continue;
                    }

                    // Check if offering already exists
                    $existingOffering = CourseOffering::where('semester_id', $semester->id)
                        ->where('curriculum_unit_id', $curriculumUnit->id)
                        ->first();

                    if ($existingOffering) {
                        $errors[] = "Course offering already exists for unit ID {$unitId}";
                        continue;
                    }

                    CourseOffering::create([
                        'semester_id' => $semester->id,
                        'curriculum_unit_id' => $curriculumUnit->id,
                        'max_capacity' => $defaultCapacity,
                        'current_enrollment' => 0,
                        'waitlist_capacity' => 10,
                        'current_waitlist' => 0,
                        'delivery_mode' => $deliveryMode,
                        'is_active' => true,
                        'enrollment_status' => 'open',
                    ]);

                    $offeringsCreated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to create offering for unit ID {$unitId}: {$e->getMessage()}";
                    Log::error('Course offering creation failed', [
                        'semester_id' => $semester->id,
                        'unit_id' => $unitId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$offeringsCreated} course offerings",
                'offerings_created' => $offeringsCreated,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk open courses failed', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk create course offerings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 3B: Open single course offering with custom details
     */
    public function openSingleCourse(Request $request, Semester $semester): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'required|integer|exists:units,id',
            'lecture_id' => 'nullable|integer|exists:lectures,id',
            'section_code' => 'nullable|string|max:10',
            'max_capacity' => 'required|integer|min:1|max:500',
            'waitlist_capacity' => 'nullable|integer|min:0|max:100',
            'delivery_mode' => 'required|in:in_person,online,hybrid,blended',
            'schedule_days' => 'nullable|array',
            'schedule_days.*' => 'string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedule_time_start' => 'nullable|date_format:H:i',
            'schedule_time_end' => 'nullable|date_format:H:i|after:schedule_time_start',
            'location' => 'nullable|string|max:255',
            'special_requirements' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Find curriculum unit for this unit
            $curriculumUnit = CurriculumUnit::where('unit_id', $validated['unit_id'])->first();

            if (!$curriculumUnit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No curriculum unit found for this unit',
                ], 400);
            }

            // Check if offering already exists
            $existingOffering = CourseOffering::where('semester_id', $semester->id)
                ->where('curriculum_unit_id', $curriculumUnit->id)
                ->where('section_code', $validated['section_code'] ?? null)
                ->first();

            if ($existingOffering) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course offering already exists for this unit and section',
                ], 200);
            }

            $courseOffering = CourseOffering::create([
                'semester_id' => $semester->id,
                'curriculum_unit_id' => $curriculumUnit->id,
                'lecture_id' => $validated['lecture_id'] ?? null,
                'section_code' => $validated['section_code'] ?? null,
                'max_capacity' => $validated['max_capacity'],
                'current_enrollment' => 0,
                'waitlist_capacity' => $validated['waitlist_capacity'] ?? 10,
                'current_waitlist' => 0,
                'delivery_mode' => $validated['delivery_mode'],
                'schedule_days' => $validated['schedule_days'] ?? null,
                'schedule_time_start' => $validated['schedule_time_start'] ?? null,
                'schedule_time_end' => $validated['schedule_time_end'] ?? null,
                'location' => $validated['location'] ?? null,
                'is_active' => true,
                'enrollment_status' => 'open',
                'special_requirements' => $validated['special_requirements'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Course offering created successfully',
                'course_offering' => $courseOffering->load(['unit', 'lecture', 'semester']),
            ]);
        } catch (\Exception $e) {
            Log::error('Open single course failed', [
                'semester_id' => $semester->id,
                'unit_id' => $validated['unit_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create course offering: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 4: Get registration statistics
     */
    public function getRegistrationStats(Semester $semester, Request $request): JsonResponse
    {
        try {
            $query = CourseOffering::where('semester_id', $semester->id)
                ->with(['curriculumUnit.unit', 'lecture', 'courseRegistrations.student']);

            // Apply filters
            if ($request->filled('unit_id')) {
                $query->whereHas('curriculumUnit.unit', function ($q) use ($request) {
                    $q->where('unit_id', $request->unit_id);
                });
            }

            if ($request->filled('lecture_id')) {
                $query->where('lecture_id', $request->lecture_id);
            }

            if ($request->filled('enrollment_status')) {
                $query->where('enrollment_status', $request->enrollment_status);
            }

            $courseOfferings = $query->get();

            $stats = [
                'total_offerings' => $courseOfferings->count(),
                'by_status' => $courseOfferings->groupBy('enrollment_status')->map->count(),
                'enrollment_summary' => [
                    'total_capacity' => $courseOfferings->sum('max_capacity'),
                    'total_enrolled' => $courseOfferings->sum('current_enrollment'),
                    'total_waitlisted' => $courseOfferings->sum('current_waitlist'),
                    'enrollment_rate' => 0,
                ],
                'offerings' => $courseOfferings->map(function ($offering) {
                    return [
                        'id' => $offering->id,
                        'unit_code' => $offering->unit?->code,
                        'unit_name' => $offering->unit?->name,
                        'section_code' => $offering->section_code,
                        'instructor_name' => $offering->lecturer?->display_name,
                        'max_capacity' => $offering->max_capacity,
                        'current_enrollment' => $offering->current_enrollment,
                        'waitlist_capacity' => $offering->waitlist_capacity,
                        'current_waitlist' => $offering->current_waitlist,
                        'enrollment_status' => $offering->enrollment_status,
                        'enrollment_rate' => $offering->max_capacity > 0
                            ? round(($offering->current_enrollment / $offering->max_capacity) * 100, 2)
                            : 0,
                    ];
                }),
            ];

            // Calculate overall enrollment rate
            if ($stats['enrollment_summary']['total_capacity'] > 0) {
                $stats['enrollment_summary']['enrollment_rate'] = round(
                    ($stats['enrollment_summary']['total_enrolled'] / $stats['enrollment_summary']['total_capacity']) * 100,
                    2
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration statistics loaded successfully',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Get registration stats failed', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get registration statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 5: Bulk register enrolled students for available course offerings
     */
    public function bulkRegisterStudents(Semester $semester, Request $request): JsonResponse
    {
        $request->validate([
            'registration_method' => 'nullable|in:online,advisor,admin_override',
            'force_registration' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            // Get current campus ID from session
            $currentCampusId = session()->get('current_campus_id');

            if (!$currentCampusId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No campus selected. Please select a campus first.',
                ], 400);
            }

            $registrationMethod = $request->registration_method ?? 'admin_override';
            $forceRegistration = $request->force_registration ?? false;

            // Get all enrollments for this semester that are in progress filtered by campus
            $enrollments = Enrollment::where('semester_id', $semester->id)
                ->where('status', 'in_progress')
                ->whereHas('student', function ($query) use ($currentCampusId) {
                    $query->where('campus_id', $currentCampusId);
                })
                ->with(['student', 'curriculumVersion'])
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active enrollments found for this semester in the selected campus',
                ], 200);
            }

            $registrationsCreated = 0;
            $errors = [];
            $warnings = [];
            $skipped = 0;

            foreach ($enrollments as $enrollment) {
                try {
                    $student = $enrollment->student;

                    // Skip inactive students
                    if ($student->status !== 'active') {
                        $warnings[] = "Skipped student {$student->student_code} - not active";
                        $skipped++;
                        continue;
                    }

                    // Get curriculum units for this enrollment
                    $curriculumUnits = CurriculumUnit::where('curriculum_version_id', $enrollment->curriculum_version_id)
                        ->where('semester_number', $enrollment->semester_number)
                        ->with('unit')
                        ->get();

                    if ($curriculumUnits->isEmpty()) {
                        $warnings[] = "No curriculum units found for student {$student->student_code} in semester {$enrollment->semester_number}";
                        $skipped++;
                        continue;
                    }

                    $studentRegistrations = 0;

                    foreach ($curriculumUnits as $curriculumUnit) {
                        try {
                            // Find available course offering for this unit
                            $courseOffering = CourseOffering::where('semester_id', $semester->id)
                                ->where('curriculum_unit_id', $curriculumUnit->id)
                                ->where('is_active', true)
                                ->where('enrollment_status', 'open')
                                ->first();

                            if (!$courseOffering) {
                                $warnings[] = "No course offering found for unit {$curriculumUnit->unit->code} (Student: {$student->student_code})";
                                continue;
                            }

                            // Check if student is already registered
                            $existingRegistration = \App\Models\CourseRegistration::where('student_code', $student->id)
                                ->where('course_offering_id', $courseOffering->id)
                                ->where('semester_id', $semester->id)
                                ->whereIn('registration_status', ['registered', 'confirmed'])
                                ->exists();

                            if ($existingRegistration) {
                                $warnings[] = "Student {$student->student_code} already registered for {$curriculumUnit->unit->code}";
                                continue;
                            }

                            // Check capacity (unless forced)
                            if (!$forceRegistration && $courseOffering->current_enrollment >= $courseOffering->max_capacity) {
                                $warnings[] = "Course {$curriculumUnit->unit->code} is at capacity (Student: {$student->student_code})";
                                continue;
                            }

                            // Create registration
                            \App\Models\CourseRegistration::create([
                                'student_code' => $student->id,
                                'course_offering_id' => $courseOffering->id,
                                'semester_id' => $semester->id,
                                'registration_status' => 'confirmed',
                                'registration_date' => now(),
                                'registration_method' => $registrationMethod,
                                'credit_hours' => $curriculumUnit->unit->credit_points ?? 3,
                                'notes' => "Bulk registration via admin",
                            ]);

                            // Update course offering enrollment count
                            $courseOffering->increment('current_enrollment');

                            // Update status if at capacity
                            if ($courseOffering->current_enrollment >= $courseOffering->max_capacity) {
                                $courseOffering->update(['enrollment_status' => 'closed']);
                            }

                            $registrationsCreated++;
                            $studentRegistrations++;
                        } catch (\Exception $e) {
                            $errors[] = "Failed to register student {$student->student_code} for {$curriculumUnit->unit->code}: {$e->getMessage()}";
                            Log::error('Course registration failed', [
                                'student_code' => $student->id,
                                'course_offering_id' => $courseOffering->id ?? 'unknown',
                                'unit_code' => $curriculumUnit->unit->code,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    if ($studentRegistrations === 0) {
                        $warnings[] = "No registrations created for student {$student->student_code}";
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to process enrollment for student {$student->student_code}: {$e->getMessage()}";
                    $skipped++;
                    Log::error('Student enrollment processing failed', [
                        'student_code' => $student->id,
                        'enrollment_id' => $enrollment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            $message = "Bulk registration completed. Created {$registrationsCreated} registrations.";
            if ($skipped > 0) {
                $message .= " Skipped {$skipped} students.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'registrations_created' => $registrationsCreated,
                'students_processed' => $enrollments->count(),
                'students_skipped' => $skipped,
                'warnings' => array_slice($warnings, 0, 10), // Limit warnings to avoid huge response
                'errors' => array_slice($errors, 0, 10), // Limit errors to avoid huge response
                'total_warnings' => count($warnings),
                'total_errors' => count($errors),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk register students failed', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk register students: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get registrable students and their available courses
     */
    public function getRegistrableStudents(Semester $semester, Request $request): JsonResponse
    {
        try {
            // Get current campus ID from session
            $currentCampusId = session()->get('current_campus_id');

            if (!$currentCampusId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No campus selected. Please select a campus first.',
                ], 400);
            }

            // Get all enrollments for this semester that are in progress filtered by campus
            $enrollments = Enrollment::where('semester_id', $semester->id)
                ->where('status', 'in_progress')
                ->whereHas('student', function ($query) use ($currentCampusId) {
                    $query->where('campus_id', $currentCampusId);
                })
                ->with(['student', 'curriculumVersion'])
                ->get();

            $studentsWithCourses = [];
            $totalAvailableRegistrations = 0;
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student;

                // Skip inactive students
                if ($student->status !== 'active') {
                    continue;
                }

                // Get curriculum units for this enrollment
                $curriculumUnits = CurriculumUnit::where('curriculum_version_id', $enrollment->curriculum_version_id)
                    ->where('semester_number', $enrollment->semester_number)
                    ->with('unit')
                    ->get();

                $availableCourses = [];

                foreach ($curriculumUnits as $curriculumUnit) {
                    // Find available course offering for this unit
                    $courseOffering = CourseOffering::where('semester_id', $semester->id)
                        ->where('curriculum_unit_id', $curriculumUnit->id)
                        ->where('is_active', true)
                        ->where('enrollment_status', 'open')
                        ->first();

                    if (!$courseOffering) {
                        continue;
                    }

                    // Check if student is already registered
                    $alreadyRegistered = \App\Models\CourseRegistration::where('student_code', $student->id)
                        ->where('course_offering_id', $courseOffering->id)
                        ->where('semester_id', $semester->id)
                        ->whereIn('registration_status', ['registered', 'confirmed'])
                        ->exists();

                    if ($alreadyRegistered) {
                        continue;
                    }

                    $hasCapacity = $courseOffering->current_enrollment < $courseOffering->max_capacity;

                    $availableCourses[] = [
                        'unit_code' => $curriculumUnit->unit->code,
                        'unit_name' => $curriculumUnit->unit->name,
                        'course_offering_id' => $courseOffering->id,
                        'is_required' => $curriculumUnit->is_required ?? true,
                        'has_capacity' => $hasCapacity,
                        'current_enrollment' => $courseOffering->current_enrollment,
                        'max_capacity' => $courseOffering->max_capacity,
                    ];

                    if ($hasCapacity) {
                        $totalAvailableRegistrations++;
                    }
                }
                Log::info('Available Courses', ['available_courses' => $availableCourses]);
                if (!empty($availableCourses)) {
                    $studentsWithCourses[] = [
                        'student_code' => $student->student_code,
                        'student_name' => $student->full_name,
                        'semester_number' => $enrollment->semester_number,
                        'available_courses' => $availableCourses,
                        'total_courses' => count($availableCourses),
                        'available_with_capacity' => count(array_filter($availableCourses, fn($course) => $course['has_capacity'])),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Registrable students loaded successfully',
                'data' => [
                    'students' => $studentsWithCourses,
                    'summary' => [
                        'total_students' => count($studentsWithCourses),
                        'total_enrollments' => $enrollments->count(),
                        'total_available_registrations' => $totalAvailableRegistrations,
                        'avg_courses_per_student' => count($studentsWithCourses) > 0
                            ? round(array_sum(array_column($studentsWithCourses, 'total_courses')) / count($studentsWithCourses), 2)
                            : 0,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get registrable students failed', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get registrable students: ' . $e->getMessage(),
            ], 500);
        }
    }
}
