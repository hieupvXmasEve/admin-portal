<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AcademicHold;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumUnit;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Actions\EnrollStudentInCourseOfferingAction;
use App\Modules\Academic\Delivery\Actions\OpenSingleCourseOfferingAction;
use App\Modules\Academic\Delivery\Exceptions\InstructorAssignmentException;
use App\Modules\Academic\Http\Requests\CourseDelivery\OpenSingleCourseOfferingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SemesterEnrollmentController extends Controller
{
    /**
     * Show the semester enrollment management page
     */
    public function show(Semester $semester): Response
    {
        // Get current campus ID from session
        $currentCampusId = session()->get('current_campus_id');

        // Load semester data with campus-filtered enrollments
        $semester->load([
            'enrollments' => function ($query) use ($currentCampusId) {
                if ($currentCampusId) {
                    $query->whereHas('student', function ($q) use ($currentCampusId) {
                        $q->where('campus_id', $currentCampusId);
                    });
                }
            },
            'enrollments.student.curriculumVersion',
            'courseOfferings.unit',
        ]);

        // Campus-filtered enrollment statistics
        $enrollmentQuery = $semester->enrollments();
        if ($currentCampusId) {
            $enrollmentQuery->whereHas('student', function ($query) use ($currentCampusId) {
                $query->where('campus_id', $currentCampusId);
            });
        }

        $enrollmentStats = [
            'total_enrolled' => $enrollmentQuery->count(),
            'by_status' => (clone $enrollmentQuery)
                ->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'by_semester_number' => (clone $enrollmentQuery)
                ->groupBy('semester_number')
                ->selectRaw('semester_number, count(*) as count')
                ->orderBy('semester_number')
                ->pluck('count', 'semester_number')
                ->toArray(),
        ];

        // Get campus-specific student statistics
        $campusStats = [];
        if ($currentCampusId) {
            $campus = Campus::find($currentCampusId);

            // Get total eligible students for this campus
            $totalEligibleStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->count();

            // Get students already enrolled for this semester
            $enrolledStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->whereHas('enrollments', function ($query) use ($semester) {
                    $query->where('semester_id', $semester->id);
                })
                ->count();

            // Get students not yet enrolled for this semester
            $notEnrolledStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->whereDoesntHave('enrollments', function ($query) use ($semester) {
                    $query->where('semester_id', $semester->id);
                })
                ->whereDoesntHave('academicHolds', function ($query) {
                    $query->where('hold_category', 'registration')->where('status', 'active');
                })
                ->count();

            $campusStats = [
                'campus_name' => $campus->name ?? 'Unknown Campus',
                'campus_code' => $campus->code ?? 'N/A',
                'total_eligible_students' => $totalEligibleStudents,
                'enrolled_students' => $enrolledStudents,
                'not_enrolled_students' => $notEnrolledStudents,
                'enrollment_rate' => $totalEligibleStudents > 0
                    ? round(($enrolledStudents / $totalEligibleStudents) * 100, 1)
                    : 0,
            ];
        }

        return Inertia::render('Semesters/Enrollment', [
            'semester' => $semester,
            'enrollmentStats' => $enrollmentStats,
            'campusStats' => $campusStats,
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

            if (! $currentCampusId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No campus selected. Please select a campus first.',
                ], 400);
            }

            // Get active students with valid curriculum_version_id for current campus
            $eligibleStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->whereDoesntHave('enrollments', fn ($q) => $q->where('semester_id', $semester->id))
                ->whereDoesntHave('academicHolds', fn ($q) => $q->where('hold_category', 'registration')->where('status', 'active'))
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
                    $latestEnrollment = Enrollment::where('student_id', $student->id)
                        ->orderBy('semester_number', 'desc')
                        ->first();

                    $semesterNumber = $latestEnrollment ? $latestEnrollment->semester_number + 1 : 1;

                    // Validate semester number doesn't exceed reasonable limits
                    if ($semesterNumber > 8) {
                        $errors[] = "Student {$student->student_id} has exceeded maximum semester limit";

                        continue;
                    }

                    Enrollment::create([
                        'student_id' => $student->id,
                        'semester_id' => $semester->id,
                        'curriculum_version_id' => $student->curriculum_version_id,
                        'semester_number' => $semesterNumber,
                        'status' => 'in_progress',
                    ]);

                    $enrollmentsCreated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to enroll student {$student->student_id}: {$e->getMessage()}";
                    Log::error('Enrollment creation failed', [
                        'student_id' => $student->id,
                        'semester_id' => $semester->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            // Get updated campus statistics
            $campus = Campus::find($currentCampusId);
            $totalEligibleStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->count();
            $campusName = $campus->name ?? 'campus';

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$enrollmentsCreated} enrollments for {$campusName}",
                'enrollments_created' => $enrollmentsCreated,
                'total_eligible_students' => $totalEligibleStudents,
                'campus_name' => $campus->name ?? 'Unknown Campus',
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
                'message' => 'Failed to generate enrollments: '.$e->getMessage(),
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

            if (! $currentCampusId) {
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
                return $enrollment->curriculum_version_id.'_'.$enrollment->semester_number;
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

                    if (! isset($unitDemand[$unitId])) {
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
            $existingOfferings = CourseOffering::where('semester_id', $semester->id)
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('unit_id')
                ->groupBy('unit_id')
                ->selectRaw('unit_id, count(*) as offering_count')
                ->pluck('offering_count', 'unit_id')
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
                'message' => 'Failed to get suggested courses: '.$e->getMessage(),
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
                    // Check if any offering already exists for this unit (across all curriculum versions)
                    $existingOffering = CourseOffering::where('semester_id', $semester->id)
                        ->where('campus_id', app('campus')->id)
                        ->whereHas('curriculumUnit', function ($query) use ($unitId) {
                            $query->where('unit_id', $unitId);
                        })
                        ->first();

                    if ($existingOffering) {
                        $errors[] = "Course offering already exists for unit ID {$unitId}";

                        continue;
                    }

                    $courseOffering = CourseOffering::create([
                        'campus_id' => app('campus')->id,
                        'semester_id' => $semester->id,
                        'unit_id' => $unitId,
                        'max_capacity' => $defaultCapacity,
                        'current_enrollment' => 0,
                        'waitlist_capacity' => 10,
                        'current_waitlist' => 0,
                        'delivery_mode' => $deliveryMode,
                        'is_active' => true,
                        'enrollment_status' => 'open',
                        'notes' => 'Unified offering for all curriculum versions',
                    ]);

                    $offeringsCreated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to create offering for unit ID {$unitId}: {$e->getMessage()}";
                    Log::error('Course offering creation failed', [
                        'semester_id' => $semester->id,
                        'unit_id' => $unitId,
                        'error' => $e->getMessage(),
                        'stack_trace' => $e->getTraceAsString(),
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
                'message' => 'Failed to bulk create course offerings: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 3B: Open single course offering with custom details
     */
    public function openSingleCourse(OpenSingleCourseOfferingRequest $request, Semester $semester): JsonResponse
    {
        $validated = $request->validated();

        try {
            $courseOffering = OpenSingleCourseOfferingAction::run([
                'semester_id' => (int) $semester->id,
                'campus_id' => (int) session('current_campus_id'),
                'attributes' => $validated,
            ]);

            if ($courseOffering === null) {
                return ApiResponse::error('Course offering already exists for this unit and section', [], 200);
            }

            return ApiResponse::success(
                ['course_offering' => $courseOffering->load(['unit', 'lecture', 'semester'])],
                [],
                'Course offering created successfully',
            );
        } catch (InstructorAssignmentException $exception) {
            return ApiResponse::validationError([
                $exception->field => [$exception->getMessage()],
            ]);
        } catch (\Exception $e) {
            Log::error('Open single course failed', [
                'semester_id' => $semester->id,
                'unit_id' => $validated['unit_id'],
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to create course offering: '.$e->getMessage());
        }
    }

    /**
     * Step 4: Get registration statistics
     */
    public function getRegistrationStats(Semester $semester, Request $request): JsonResponse
    {
        try {
            $query = CourseOffering::where('semester_id', $semester->id)
                ->with(['unit', 'lecture', 'courseRegistrations.student']);

            // Apply filters
            if ($request->filled('unit_id')) {
                $query->whereHas('unit', function ($q) use ($request) {
                    $q->where('id', $request->unit_id);
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
                'message' => 'Failed to get registration statistics: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 5: Bulk register enrolled students for available course offerings
     */
    public function bulkRegisterStudents(Semester $semester, Request $request): JsonResponse
    {
        // Input validation & sanitization
        $validated = $request->validate([
            'registration_method' => 'nullable|in:online,advisor,admin_override',
            'force_registration' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:50|max:500',
            'preferred_instructor_ids' => 'nullable|array',
            'preferred_instructor_ids.*' => 'integer',
        ]);

        try {
            // Get current campus ID from session
            $currentCampusId = session()->get('current_campus_id');

            if (! $currentCampusId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No campus selected. Please select a campus first.',
                ], 400);
            }

            $registrationMethod = $validated['registration_method'] ?? 'admin_override';
            $forceRegistration = (bool) ($validated['force_registration'] ?? false);
            $preferredInstructorIds = collect($validated['preferred_instructor_ids'] ?? []);

            Log::info('Starting bulk student registration', [
                'semester_id' => $semester->id,
                'campus_id' => $currentCampusId,
                'registration_method' => $registrationMethod,
                'force_registration' => $forceRegistration,
                'page' => $validated['page'] ?? 1,
                'per_page' => $validated['per_page'] ?? 300,
            ]);

            // 1) Enrollment window validation (semester-level) - Disabled for admin override
            // Admin can override enrollment window restrictions
            //            if (!$forceRegistration && method_exists($semester, 'isRegistrationOpen') && !$semester->isRegistrationOpen()) {
            //                $msg = 'Semester enrollment window is closed. Use force registration to override.';
            //                Log::warning($msg, [
            //                    'semester_id' => $semester->id,
            //                    'enrollment_start_date' => $semester->enrollment_start_date,
            //                    'enrollment_end_date' => $semester->enrollment_end_date,
            //                ]);
            //
            //                return response()->json([
            //                    'success' => false,
            //                    'message' => $msg,
            //                    'data' => [
            //                        'enrollment_start_date' => $semester->enrollment_start_date,
            //                        'enrollment_end_date' => $semester->enrollment_end_date,
            //                        'force_registration_available' => true,
            //                    ],
            //                ], 422);
            //            }

            // Get all enrollments for this semester that are in progress filtered by campus
            $enrollmentsQuery = Enrollment::query()
                ->where('semester_id', $semester->id)
                ->where('status', 'in_progress')
                ->whereHas('student', function ($query) use ($currentCampusId) {
                    $query->where('campus_id', $currentCampusId);
                })
                ->with(['student', 'curriculumVersion']);

            // Pagination for large requests
            $page = (int) ($validated['page'] ?? 1);
            $perPage = (int) ($validated['per_page'] ?? 300);
            $totalEnrollments = (clone $enrollmentsQuery)->count();
            $enrollments = (clone $enrollmentsQuery)
                ->orderBy('id')
                ->forPage($page, $perPage)
                ->get();

            if ($enrollments->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active enrollments found for this page in the selected campus',
                    'data' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $totalEnrollments,
                    ],
                ], 200);
            }

            $registrationsCreated = 0;
            $skipped = 0;
            $results = [
                'success' => [],
                'skipped' => [],
                'failed' => [],
            ];

            // Process in batches (idempotent, with row-level locking for counts)
            // Wrap each page/batch in a transaction to prevent oversized transactions
            DB::transaction(function () use (
                $enrollments,
                $semester,
                $registrationMethod,
                $forceRegistration,
                $preferredInstructorIds,
                &$registrationsCreated,
                &$skipped,
                &$results,
                $currentCampusId
            ) {
                foreach ($enrollments as $enrollment) {
                    $student = $enrollment->student;

                    try {
                        // Basic student status checks
                        if (! in_array($student->status, ['intake_pre_uni_gc', 'intake_course']) || $student->academic_status !== 'active') {
                            $skipped++;
                            $results['skipped'][] = [
                                'student_id' => $student->id,
                                'student_id' => $student->student_id,
                                'reason' => 'Student not active',
                            ];

                            continue;
                        }

                        // 2) Academic hold blocking (registration holds)
                        $activeHolds = AcademicHold::query()
                            ->where('student_id', $student->id)
                            ->whereIn('hold_category', ['registration', 'all'])
                            ->where('status', 'active')
                            ->get(['id', 'hold_type', 'hold_category', 'title', 'description', 'amount', 'priority', 'due_date']);

                        if ($activeHolds->isNotEmpty()) {
                            $skipped++;
                            $results['failed'][] = [
                                'student_id' => $student->id,
                                'student_id' => $student->student_id,
                                'error' => 'Active registration holds',
                                'holds' => $activeHolds->toArray(),
                            ];
                            Log::warning('Blocked registration due to active holds', [
                                'student_id' => $student->id,
                                'student_student_id' => $student->student_id,
                                'holds' => $activeHolds->pluck('id')->all(),
                            ]);

                            continue;
                        }

                        // Get planned curriculum units for this enrollment
                        $curriculumUnits = CurriculumUnit::query()
                            ->where('curriculum_version_id', $enrollment->curriculum_version_id)
                            ->where('semester_number', $enrollment->semester_number)
                            ->with('unit')
                            ->get();

                        if ($curriculumUnits->isEmpty()) {
                            $skipped++;
                            $results['skipped'][] = [
                                'student_id' => $student->id,
                                'student_id' => $student->student_id,
                                'reason' => 'No curriculum units in this semester',
                            ];

                            continue;
                        }

                        // Preload student's completed units for prerequisite/duplicate checks
                        $completedUnitIds = AcademicRecord::query()
                            ->where('student_id', $student->id)
                            ->where(function ($q) {
                                $q->where('completion_status', 'completed')
                                    ->orWhere('credit_hours_earned', '>', 0);
                            })
                            ->pluck('unit_id')
                            ->filter()
                            ->unique()
                            ->all();

                        // Preload student's current active registrations to detect schedule conflicts
                        $activeRegs = CourseRegistration::query()
                            ->where('student_id', $student->id)
                            ->where('semester_id', $semester->id)
                            ->whereIn('registration_status', ['pending', 'registered', 'confirmed'])
                            ->with(['courseOffering:id,semester_id,unit_id,schedule_days,schedule_time_start,schedule_time_end,lecture_id'])
                            ->get();

                        foreach ($curriculumUnits as $curriculumUnit) {
                            $unitCode = $curriculumUnit->unit?->code ?? (string) $curriculumUnit->unit_id;

                            // 3) Duplicate enrollment prevention via academic records
                            $hasCredit = AcademicRecord::query()
                                ->where('student_id', $student->id)
                                ->where('unit_id', $curriculumUnit->unit_id)
                                ->where(function ($q) {
                                    $q->where('completion_status', 'completed')
                                        ->orWhere('credit_hours_earned', '>', 0);
                                })
                                ->exists();
                            if ($hasCredit) {
                                $skipped++;
                                $results['skipped'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'reason' => 'Completed or credit already earned',
                                ];

                                continue;
                            }

                            // 4) Prerequisite validation
                            $prereqGroups = $curriculumUnit->unit
                                ?->prerequisiteGroups()
                                ->with('conditions.requiredUnit')
                                ->get() ?? collect();

                            $prereqFailed = false;
                            $missingPrereqs = [];

                            foreach ($prereqGroups as $group) {
                                $logic = strtoupper($group->logic_operator ?? 'AND');
                                $conditions = $group->conditions;
                                if ($conditions->isEmpty()) {
                                    continue;
                                }

                                $evals = [];
                                foreach ($conditions as $cond) {
                                    $type = strtolower($cond->type ?? 'prerequisite');
                                    if ($cond->requiredUnit) {
                                        $reqId = $cond->requiredUnit->id;
                                        $met = in_array($reqId, $completedUnitIds, true);
                                        if (! $met && in_array($type, ['co_requisite', 'concurrent_prerequisite'])) {
                                            // Allow concurrent enrollment only if this required unit is also in the plan
                                            $met = $curriculumUnits->contains(fn ($cu) => $cu->unit_id === $reqId);
                                        }
                                        $evals[] = $met;
                                        if (! $met) {
                                            $missingPrereqs[] = [
                                                'type' => $type,
                                                'unit' => [
                                                    'id' => $reqId,
                                                    'code' => $cond->requiredUnit->code,
                                                    'name' => $cond->requiredUnit->name,
                                                ],
                                                'group_operator' => $group->logic_operator,
                                            ];
                                        }
                                    } else {
                                        // For non-unit conditions (credits/free_text), treat as not blocking for now
                                        $evals[] = true;
                                    }
                                }

                                $groupOk = $logic === 'OR'
                                    ? in_array(true, $evals, true)
                                    : ! in_array(false, $evals, true);
                                if (! $groupOk) {
                                    $prereqFailed = true;
                                }
                            }

                            if ($prereqFailed) {
                                $results['failed'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'error' => 'Prerequisites not satisfied',
                                    'missing_prerequisites' => $missingPrereqs,
                                ];
                                Log::warning('Prerequisite validation failed', [
                                    'student_id' => $student->id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'missing' => $missingPrereqs,
                                ]);

                                continue;
                            }

                            // 7) Section selection with deterministic logic - Search by unit_id since we now have unified offerings
                            $offerings = CourseOffering::query()
                                ->where('semester_id', $semester->id)
                                ->where('campus_id', $currentCampusId)
                                ->whereHas('curriculumUnit', function ($query) use ($curriculumUnit) {
                                    $query->where('unit_id', $curriculumUnit->unit_id);
                                })
                                ->active()
                                ->where('enrollment_status', 'open')
                                ->registrationOpen()
                                ->get();

                            if ($offerings->isEmpty()) {
                                $results['failed'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'error' => 'No available course offering or registration closed',
                                ];

                                continue;
                            }

                            // Build a conflict-aware, preference-aware sorted list
                            $studentSlots = $activeRegs->pluck('courseOffering');
                            $sorted = $offerings->sortByDesc(function (CourseOffering $o) use ($studentSlots, $preferredInstructorIds) {
                                $hasConflict = $this->hasScheduleConflict($o, $studentSlots);
                                $prefInstr = $preferredInstructorIds->contains($o->lecture_id);
                                $available = max(0, (int) $o->max_capacity - (int) $o->current_enrollment);

                                // Priority: no conflict (true=1), instructor preferred (true=1), capacity
                                return (int) (! $hasConflict) * 1_000_000
                                    + (int) ($prefInstr) * 10_000
                                    + $available;
                            })->values();

                            $selectedOffering = $sorted->first();
                            if (! $selectedOffering) {
                                $results['failed'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'error' => 'No suitable section found',
                                ];

                                continue;
                            }

                            // 8) Validate course offering registration dates (already constrained via registrationOpen())
                            if (! $selectedOffering->isRegistrationOpen()) {
                                $results['failed'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'error' => 'Course offering registration window closed',
                                ];

                                continue;
                            }

                            // 5) Idempotent operations & capacity handling with row-level locking
                            // Lock the selected offering row first (prevents race on capacity)
                            $offeringLocked = CourseOffering::query()
                                ->where('id', $selectedOffering->id)
                                ->lockForUpdate()
                                ->first();

                            if (! $offeringLocked) {
                                $results['failed'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'error' => 'Failed to lock course offering',
                                ];

                                continue;
                            }

                            // Re-check duplicate under lock
                            $alreadyRegistered = CourseRegistration::query()
                                ->where('student_id', $student->id)
                                ->where('course_offering_id', $offeringLocked->id)
                                ->where('semester_id', $semester->id)
                                ->whereIn('registration_status', ['pending', 'registered', 'confirmed'])
                                ->exists();
                            if ($alreadyRegistered) {
                                $skipped++;
                                $results['skipped'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'reason' => 'Already registered',
                                ];

                                continue;
                            }

                            // Capacity check
                            $hasCapacity = (int) $offeringLocked->current_enrollment < (int) $offeringLocked->max_capacity;
                            if (! $hasCapacity && ! $forceRegistration) {
                                $results['skipped'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'reason' => 'Offering at capacity',
                                ];
                                $skipped++;

                                continue;
                            }

                            try {
                                EnrollStudentInCourseOfferingAction::run([
                                    'student_id' => $student->id,
                                    'course_offering_id' => $offeringLocked->id,
                                    'registration_status' => 'confirmed',
                                    'registration_method' => $registrationMethod,
                                    'force_registration' => $forceRegistration,
                                    'credit_hours' => $curriculumUnit->unit->credit_points ?? 3,
                                    'notes' => 'Bulk registration via admin',
                                ]);
                            } catch (\Throwable $ex) {
                                // Unique constraint hit or other issue
                                Log::warning('Registration insert conflict or error', [
                                    'student_id' => $student->id,
                                    'student_student_id' => $student->student_id,
                                    'course_offering_id' => $offeringLocked->id,
                                    'unit_code' => $unitCode,
                                    'error' => $ex->getMessage(),
                                    'error_code' => $ex->getCode(),
                                ]);
                                $skipped++;
                                $results['skipped'][] = [
                                    'student_id' => $student->id,
                                    'student_id' => $student->student_id,
                                    'unit_id' => $curriculumUnit->unit_id,
                                    'unit_code' => $unitCode,
                                    'reason' => 'Duplicate or insert failed',
                                ];

                                continue;
                            }

                            $registrationsCreated++;
                            $results['success'][] = [
                                'student_id' => $student->id,
                                'student_id' => $student->student_id,
                                'unit_id' => $curriculumUnit->unit_id,
                                'unit_code' => $unitCode,
                                'course_offering_id' => $offeringLocked->id,
                            ];
                        }
                    } catch (\Throwable $e) {
                        $results['failed'][] = [
                            'student_id' => $student->id ?? null,
                            'student_id' => $student->student_id ?? null,
                            'error' => 'Processing error: '.$e->getMessage(),
                        ];
                        Log::error('Student enrollment processing failed', [
                            'student_id' => $student->id ?? null,
                            'student_student_id' => $student->student_id ?? null,
                            'enrollment_id' => $enrollment->id ?? null,
                            'error' => $e->getMessage(),
                            'stack_trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }, 5); // retry up to 5 times if deadlock

            $remaining = max(0, $totalEnrollments - ($page * $perPage));
            $nextPage = $remaining > 0 ? $page + 1 : null;

            $message = "Bulk registration completed for page {$page}. Created {$registrationsCreated} registrations.";
            if ($skipped > 0) {
                $message .= " Skipped {$skipped} items.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'registrations_created' => $registrationsCreated,
                'batch' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'processed' => $enrollments->count(),
                    'remaining' => $remaining,
                    'next_page' => $nextPage,
                    'total' => $totalEnrollments,
                ],
                'results' => [
                    'success' => $results['success'],
                    'skipped' => $results['skipped'],
                    'failed' => $results['failed'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Bulk register students failed', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk register students: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine schedule conflicts between a candidate offering and student's existing time slots
     */
    private function hasScheduleConflict(CourseOffering $candidate, $existingOfferings): bool
    {
        if (! $candidate) {
            return false;
        }

        $daysA = $this->normalizeDays($candidate->schedule_days);
        $startA = $this->parseTime($candidate->schedule_time_start);
        $endA = $this->parseTime($candidate->schedule_time_end);

        foreach ($existingOfferings as $off) {
            if (! $off) {
                continue;
            }
            $daysB = $this->normalizeDays($off->schedule_days);
            $startB = $this->parseTime($off->schedule_time_start);
            $endB = $this->parseTime($off->schedule_time_end);

            // Any overlapping day?
            if (count(array_intersect($daysA, $daysB)) === 0) {
                continue;
            }

            // Overlap if startA < endB and startB < endA
            if ($startA !== null && $endA !== null && $startB !== null && $endB !== null) {
                if ($startA < $endB && $startB < $endA) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDays($days): array
    {
        if (is_array($days)) {
            return $days;
        }
        if (is_string($days)) {
            // Try JSON decode first, else split by comma
            $decoded = json_decode($days, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return array_filter(array_map('trim', explode(',', $days)));
        }

        return [];
    }

    private function parseTime($t): ?int
    {
        if (! $t) {
            return null;
        }
        // Expect HH:MM[:SS] – convert to seconds since midnight
        try {
            $parts = explode(':', (string) $t);
            $h = (int) ($parts[0] ?? 0);
            $m = (int) ($parts[1] ?? 0);
            $s = (int) ($parts[2] ?? 0);

            return $h * 3600 + $m * 60 + $s;
        } catch (\Throwable) {
            return null;
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

            if (! $currentCampusId) {
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
                if (! $student->isActive()) {
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
                        ->where('unit_id', $curriculumUnit->unit_id)
                        ->where('is_active', true)
                        ->where('enrollment_status', 'open')
                        ->first();

                    if (! $courseOffering) {
                        continue;
                    }

                    // Check if student is already registered
                    $alreadyRegistered = CourseRegistration::where('student_id', $student->id)
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
                if (! empty($availableCourses)) {
                    $studentsWithCourses[] = [
                        'student_id' => $student->student_id,
                        'student_name' => $student->full_name,
                        'semester_number' => $enrollment->semester_number,
                        'available_courses' => $availableCourses,
                        'total_courses' => count($availableCourses),
                        'available_with_capacity' => count(array_filter($availableCourses, fn ($course) => $course['has_capacity'])),
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
                'message' => 'Failed to get registrable students: '.$e->getMessage(),
            ], 500);
        }
    }
}
