<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\StudentRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentResource;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use App\Exports\StudentExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    public function __construct(
        private StudentService $studentService
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');
        Log::info('Current campus ID: ' . $campusId);
        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'program_id' => 'nullable|integer|exists:programs,id',
            'status' => 'nullable|string|in:active,inactive,suspended,graduated,intake_pre_uni_gc,intake_course,deferred,dropout,dropout_transfer,pending',
            'course_offering_id' => 'nullable|integer|exists:course_offerings,id',
            'sort' => 'nullable|string|in:student_id,full_name,email,admission_date,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $page = $validated['page'] ?? 1;
        $per_page = $validated['per_page'] ?? 10;
        // Always filter by current campus
        $students = Student::query()
            ->with(['campus', 'program', 'specialization'])
            ->where('campus_id', $campusId)
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('student_id', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($validated['program_id'] ?? null, function ($query, $programId) {
                $query->where('program_id', $programId);
            })
            ->when($validated['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($validated['course_offering_id'] ?? null, function ($query, $courseOfferingId) {
                $query->whereHas('courseRegistrations', function ($q) use ($courseOfferingId) {
                    $q->where('course_offering_id', $courseOfferingId);
                });
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($per_page, ['*'], 'page', $page)
            ->withQueryString();

        // Get all programs since they are not campus-specific
        $programs = Program::orderBy('name')->get(['id', 'name']);

        // Get current course offerings for the campus
        $courseOfferings = \App\Models\CourseOffering::with('unit:id,code,name')
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->whereHas('semester', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('created_at', 'desc')
            ->take(50) // Limit to recent offerings
            ->get(['id', 'unit_id', 'section_code']);

        // Get statistics for current campus
        $statistics = $this->studentService->getStudentStatistics($campusId);

        return Inertia::render('students/Index', [
            'students' => $students,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'course_offering_id' => $validated['course_offering_id'] ?? null,
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
                'per_page' => $validated['per_page'] ?? 15,
            ],
            'programs' => $programs,
            'courseOfferings' => $courseOfferings,
            'statistics' => $statistics,
            'current_campus_id' => $campusId,
        ]);
    }

    /**
     * API Index - Get paginated students collection for API consumers
     */
    public function apiIndex(Request $request): AnonymousResourceCollection
    {
        $query = Student::query()->with(['campus', 'program']);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('full_name', 'like', "%{$searchTerm}%")
                    ->orWhere('student_id', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        $students = $query->paginate($request->input('per_page', 15));

        return StudentResource::collection($students);
    }

    public function create(): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');

        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        // Get all programs since they are not campus-specific
        $programs = Program::with('specializations')
            ->orderBy('name')
            ->get();

        // Get specializations for these programs
        $programIds = $programs->pluck('id')->toArray();
        $specializations = Specialization::whereIn('program_id', $programIds)
            ->orderBy('name')
            ->get();

        // Get curriculum versions for these programs
        $curriculumVersions = CurriculumVersion::whereIn('program_id', $programIds)
            ->orderBy('version_code', 'desc')
            ->get();

        return Inertia::render('students/Create', [
            'programs' => $programs,
            'specializations' => $specializations,
            'curriculumVersions' => $curriculumVersions,
            'current_campus_id' => $campusId,
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse|StudentResource|JsonResponse
    {
        try {
            // Use the new createAdmittedStudent method
            $student = $this->studentService->createAdmittedStudent($request->validated());

            // Return redirect for web requests
            return redirect()
                ->route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $student)
                ->with('success', 'Student created and admitted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create student', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(Student $student, Request $request): Response|StudentResource
    {
        $student->load([
            'campus',
            'program',
            'specialization',
            'curriculumVersion',
            'courseRegistrations.courseOffering.curriculumUnit.unit',
            'academicHolds' => function ($query) {
                $query->orderBy('placed_date', 'desc');
            },
        ]);

        // Return JSON response for API requests
        if ($request->expectsJson()) {
            return new StudentResource($student);
        }

        // Get academic statistics for web view
        $academicStats = [
            'total_registrations' => $student->courseRegistrations()->count(),
            'completed_courses' => $student->courseRegistrations()->where('registration_status', 'completed')->count(),
            'active_registrations' => $student->courseRegistrations()->active()->count(),
            'total_credits_earned' => $student->courseRegistrations()
                ->where('registration_status', 'completed')
                ->passing()
                ->sum('credit_hours'),
            'active_holds' => $student->academicHolds()->active()->count(),
        ];

        return Inertia::render('students/Show', [
            'student' => $student,
            'academicStats' => $academicStats,
        ]);
    }

    public function edit(Student $student): Response
    {
        $student->load(['campus', 'program', 'specialization']);

        $campuses = Campus::orderBy('name')->get(['id', 'name', 'code']);
        $programs = Program::with('specializations')->orderBy('name')->get();

        // Get curriculum versions for the student's program
        $curriculumVersions = CurriculumVersion::where('program_id', $student->program_id)
            ->when($student->specialization_id, function ($query) use ($student) {
                $query->where('specialization_id', $student->specialization_id);
            })
            ->orderBy('created_at', 'desc')
            ->get(['id', 'version_code']);

        return Inertia::render('students/Edit', [
            'student' => $student,
            'campuses' => $campuses,
            'programs' => $programs,
            'curriculumVersions' => $curriculumVersions,
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse|StudentResource|JsonResponse
    {
        try {
            $updatedStudent = $this->studentService->updateStudent($student, $request->validated());

            // Return JSON response for API requests
            if ($request->expectsJson()) {
                return new StudentResource($updatedStudent);
            }

            // Return redirect for web requests
            return redirect()
                ->route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $updatedStudent)
                ->with('success', 'Student updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Show photo capture page for student
     */
    public function photoCapture(Student $student): Response
    {
        return Inertia::render('students/PhotoCapture', [
            'studentId' => $student->id,
            'returnUrl' => route(StudentRoutes::EDIT, $student),
        ]);
    }

    public function destroy(Student $student, Request $request): RedirectResponse|\Illuminate\Http\Response|JsonResponse
    {
        try {
            DB::transaction(function () use ($student) {
                // Use service to soft delete the student and all related data
                $this->studentService->deleteStudent($student);
            });

            // Return JSON response for API requests
            if ($request->expectsJson()) {
                return response()->noContent();
            }

            // Return redirect for web requests
            return redirect()
                ->route(StudentRoutes::INDEX)
                ->with('success', 'Student and all related data have been permanently deleted from database');
        } catch (\Exception $e) {
            Log::error('Failed to delete student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }


    /**
     * Get students by IDs (API endpoint)
     */
    public function getByIds(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:students,id',
        ]);

        $students = Student::with(['campus', 'program'])
            ->whereIn('id', $request->ids)
            ->get();

        return StudentResource::collection($students);
    }

    /**
     * Update student status
     */
    public function updateStatus(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,inactive,suspended,graduated',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $updatedStudent = $this->studentService->updateStudentStatus(
                $student,
                $validated['status'],
                $validated['reason'] ?? null
            );

            return redirect()
                ->route(StudentRoutes::SHOW, $updatedStudent)
                ->with('success', 'Student status updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update student status', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Assign program to student
     */
    public function assignProgram(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'program_id' => 'required|integer|exists:programs,id',
            'specialization_id' => 'nullable|integer|exists:specializations,id',
            'curriculum_version_id' => 'required|integer|exists:curriculum_versions,id',
        ]);

        try {
            $updatedStudent = $this->studentService->assignProgram(
                $student,
                $validated['program_id'],
                $validated['specialization_id'],
                $validated['curriculum_version_id']
            );

            return redirect()
                ->route(StudentRoutes::SHOW, $updatedStudent)
                ->with('success', 'Student program assigned successfully');
        } catch (\Exception $e) {
            Log::error('Failed to assign program to student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get specializations for a program (AJAX)
     */
    public function getSpecializations(Request $request): array
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        return Specialization::where('program_id', $validated['program_id'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * Get curriculum versions for program/specialization (AJAX)
     */
    public function getCurriculumVersions(Request $request): array
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'specialization_id' => 'nullable|exists:specializations,id',
        ]);

        $query = CurriculumVersion::where('program_id', $validated['program_id']);

        if ($validated['specialization_id']) {
            $query->where('specialization_id', $validated['specialization_id']);
        } else {
            $query->whereNull('specialization_id');
        }

        return $query->orderBy('created_at', 'desc')
            ->get(['id', 'version_code'])
            ->map(function ($version) {
                return [
                    'id' => $version->id,
                    'version_code' => $version->version_code,
                ];
            })
            ->toArray();
    }

    /**
     * Search students (API endpoint)
     */
    public function apiSearch(Request $request)
    {
        $validated = $request->validate([
            'query' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,suspended,graduated',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = Student::query()
            ->with(['campus', 'program', 'specialization']);

        // Apply status filter
        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        } else {
            $query->where('status', 'active'); // Default to active students
        }

        // Apply search query
        if ($searchQuery = $validated['query'] ?? null) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('student_id', 'like', "%{$searchQuery}%")
                    ->orWhere('full_name', 'like', "%{$searchQuery}%")
                    ->orWhere('email', 'like', "%{$searchQuery}%");
            });
        }

        $limit = $validated['limit'] ?? 20;
        $students = $query->orderBy('full_name')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved successfully',
            'data' => [
                'items' => $students->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'student_id' => $student->student_id,
                        'full_name' => $student->full_name,
                        'email' => $student->email,
                        'status' => $student->status,
                        'program' => $student->program ? [
                            'id' => $student->program->id,
                            'name' => $student->program->name,
                        ] : null,
                        'campus' => $student->campus ? [
                            'id' => $student->campus->id,
                            'name' => $student->campus->name,
                        ] : null,
                    ];
                }),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $limit,
                    'total' => $students->count(),
                    'last_page' => 1,
                    'has_more_pages' => false,
                ],
            ],
        ]);
    }

    /**
     * Get student by ID (API endpoint)
     */
    public function apiShow(Student $student)
    {
        $student->load(['campus', 'program', 'specialization']);

        return response()->json([
            'success' => true,
            'message' => 'Student retrieved successfully',
            'data' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status,
                'program' => $student->program ? [
                    'id' => $student->program->id,
                    'name' => $student->program->name,
                ] : null,
                'campus' => $student->campus ? [
                    'id' => $student->campus->id,
                    'name' => $student->campus->name,
                ] : null,
            ],
        ]);
    }

    /**
     * Get students by student IDs (API endpoint for bulk email)
     */
    public function getByStudentIds(Request $request)
    {
        $validated = $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'required|string',
        ]);

        $campusId = session()->get('current_campus_id');

        if (!$campusId) {
            return response()->json([
                'success' => false,
                'message' => 'No campus selected',
            ], 400);
        }

        $students = Student::with(['curriculumVersion', 'program', 'campus', 'specialization'])
            ->where('campus_id', $campusId)
            ->whereIn('student_id', $validated['student_ids'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved successfully',
            'data' => [
                'students' => $students->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'student_id' => $student->student_id,
                        'fullname' => $student->full_name,
                        'email' => $student->email,
                        'name' => $student->full_name, // alias for template compatibility
                        'curriculum_version_code' => $student->curriculumVersion?->version_code ?? null,
                        'program_name' => $student->program?->name ?? null,
                        'campus_name' => $student->campus?->name ?? null,
                        'specialization_name' => $student->specialization?->name ?? null,
                        'status' => $student->status,
                        // Additional fields that can be used as template variables in the future
                        'template_variables' => [
                            'name' => $student->full_name,
                            'email' => $student->email,
                            'student_id' => $student->student_id,
                            'program' => $student->program?->name ?? '',
                            'campus' => $student->campus?->name ?? '',
                            'specialization' => $student->specialization?->name ?? '',
                            'curriculum_version' => $student->curriculumVersion?->version_code ?? '',
                            'status' => $student->status,
                        ],
                    ];
                }),
            ],
        ]);
    }

    /**
     * Export students to Excel or CSV
     */
    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        $campusId = session()->get('current_campus_id');

        if (!$campusId) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a campus first',
                ], 400);
            }
            return back()->withErrors(['error' => 'Please select a campus first']);
        }

        $validated = $request->validate([
            'format' => 'required|string|in:xlsx,csv',
            'scope' => 'required|string|in:all,filtered',
            // Optional filters when scope is 'filtered'
            'search' => 'nullable|string|max:255',
            'program_id' => 'nullable|integer|exists:programs,id',
            'status' => 'nullable|string|in:active,inactive,suspended,graduated,intake_pre_uni_gc,intake_course,deferred,dropout,dropout_transfer,pending',
            'course_offering_id' => 'nullable|integer|exists:course_offerings,id',
        ]);

        try {
            // Build the query with campus scoping
            $query = Student::query()
                ->where('campus_id', $campusId);

            // Apply filters if scope is filtered
            if ($validated['scope'] === 'filtered') {
                if (!empty($validated['search'])) {
                    $search = $validated['search'];
                    $query->where(function ($q) use ($search) {
                        $q->where('student_id', 'like', "%{$search}%")
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                }

                if (!empty($validated['program_id'])) {
                    $query->where('program_id', $validated['program_id']);
                }

                if (!empty($validated['status'])) {
                    $query->where('status', $validated['status']);
                }

                if (!empty($validated['course_offering_id'])) {
                    $query->whereHas('courseRegistrations', function ($q) use ($validated) {
                        $q->where('course_offering_id', $validated['course_offering_id']);
                    });
                }
            }

            // Order by student_id for consistent export
            $query->orderBy('student_id');

            // Get campus info for filename
            $campus = Campus::findOrFail($campusId);
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "students_{$campus->code}_{$timestamp}.{$validated['format']}";

            // Create export instance
            $export = new StudentExport($query, $validated);

            // Log the export action
            Log::info('Student export initiated', [
                'campus_id' => $campusId,
                'campus_code' => $campus->code,
                'format' => $validated['format'],
                'scope' => $validated['scope'],
                'filters' => $validated,
                'user_id' => auth()->id(),
            ]);

            // Return the download
            return Excel::download($export, $filename);

        } catch (\Exception $e) {
            Log::error('Student export failed', [
                'campus_id' => $campusId,
                'error' => $e->getMessage(),
                'filters' => $validated,
                'user_id' => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export failed: ' . $e->getMessage(),
                ], 500);
            }
            return back()->withErrors(['error' => 'Export failed: ' . $e->getMessage()]);
        }
    }
}
