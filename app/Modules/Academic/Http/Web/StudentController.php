<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Constants\StudentRoutes;
use App\Exports\StudentExport;
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
use App\Modules\Academic\Queries\ExportStudentsQuery;
use App\Modules\Academic\Queries\ListStudentsQuery;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    private const STUDENT_STATUSES = [
        'active',
        'inactive',
        'suspended',
        'graduated',
        'intake_pre_uni_gc',
        'intake_course',
        'deferred',
        'dropout',
        'dropout_transfer',
        'pending',
        'admission_deferred',
    ];

    public function __construct(
        private StudentService $studentService
    ) {}

    public function index(Request $request, ListStudentsQuery $listStudentsQuery): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');
        Log::info('Current campus ID: '.$campusId);
        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            ...$this->studentFilterRules(),
            'sort' => 'nullable|string|in:student_id,full_name,email,admission_date,created_at,gc_starting_level,gc_current_level,gc_total_levels',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $filters = [
            ...$this->normalizeStudentFilters($validated),
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'page' => (int) ($validated['page'] ?? 1),
        ];

        $students = $listStudentsQuery->handle($filters, (int) $campusId);

        // Get all programs since they are not campus-specific
        $programs = Program::orderBy('name')->get(['id', 'name']);
        $specializations = Specialization::active()
            ->orderBy('name')
            ->get(['id', 'program_id', 'name', 'code']);
        $intakeSemesters = Semester::orderByDesc('start_date')
            ->get(['id', 'name', 'code', 'start_date', 'end_date']);

        // Get statistics for current campus
        $statistics = $this->studentService->getStudentStatistics($campusId);

        return Inertia::render('students/Index', [
            'students' => $students,
            'filters' => $filters,
            'programs' => $programs,
            'specializations' => $specializations,
            'intake_semesters' => $intakeSemesters,
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
            Inertia::flash('success', 'Student created and admitted successfully');

            return redirect()->route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $student);
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
            'courseRegistrations.courseOffering.unit',
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
        $student->load(['campus', 'program', 'specialization', 'parentProfiles.user']);

        $campuses = Campus::orderBy('name')->get(['id', 'name', 'code']);
        $programs = Program::with('specializations')->orderBy('name')->get();

        // Get curriculum versions for the student's program
        $curriculumVersions = CurriculumVersion::where('program_id', $student->program_id)
            ->when($student->specialization_id, function ($query) use ($student) {
                $query->where('specialization_id', $student->specialization_id);
            })
            ->orderBy('created_at', 'desc')
            ->get(['id', 'version_code']);

        // Get primary parent info for the form
        $primaryParent = $student->parentProfiles->first();
        $student->setAttribute('parent_user', $primaryParent?->user);

        return Inertia::render('students/Edit', [
            'student' => $student,
            'campuses' => $campuses,
            'programs' => $programs,
            'curriculumVersions' => $curriculumVersions,
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        try {
            $updatedStudent = $this->studentService->updateStudent($student, $request->validated());

            // Return redirect for web requests
            Inertia::flash('success', 'Student updated successfully');

            return redirect()->route(StudentRoutes::ACADEMIC_SUMMARY_SHOW, $updatedStudent);
        } catch (\Exception $e) {
            Log::error('Failed to update student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

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
            Inertia::flash('success', 'Student and all related data have been permanently deleted from database');

            return redirect()->route(StudentRoutes::INDEX);
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
            ->with(['campus', 'program', 'specialization'])
            ->where('campus_id', session()->get('current_campus_id'));

        // Apply status filter
        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        } else {
            // $query->active(); // Default to active students
        }

        // Apply search query
        if ($searchQuery = $validated['query'] ?? null) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('student_id', 'like', "%{$searchQuery}%")
                    ->orWhere('full_name', 'like', "%{$searchQuery}%")
                    ->orWhere('email', 'like', "%{$searchQuery}%");
            });
        }

        $limit = $validated['limit'] ?? 5;
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
                        'avatar_url' => $student->avatar_url,
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

        if (! $campusId) {
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
    public function export(Request $request, ExportStudentsQuery $exportQuery): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');

        if (! $campusId) {
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
            ...$this->studentFilterRules(),
        ]);
        $filters = [
            'format' => $validated['format'],
            'scope' => $validated['scope'],
            ...$this->normalizeStudentFilters($validated),
        ];

        try {
            $query = $exportQuery->getBuilder($campusId, $filters);

            // Get campus info for filename
            $campus = Campus::findOrFail($campusId);
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "students_{$campus->code}_{$timestamp}.{$validated['format']}";

            // Create export instance
            $export = new StudentExport($query, $filters);

            // Log the export action
            Log::info('Student export initiated', [
                'campus_id' => $campusId,
                'campus_code' => $campus->code,
                'format' => $validated['format'],
                'scope' => $validated['scope'],
                'filters' => $filters,
                'user_id' => Auth::id(),
            ]);

            // Return the download
            return Excel::download($export, $filename);
        } catch (\Exception $e) {
            Log::error('Student export failed', [
                'campus_id' => $campusId,
                'error' => $e->getMessage(),
                'filters' => $filters,
                'user_id' => Auth::id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export failed: '.$e->getMessage(),
                ], 500);
            }

            return back()->withErrors(['error' => 'Export failed: '.$e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function studentFilterRules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'student_ids' => 'nullable|array|max:100',
            'student_ids.*' => 'required|string|max:20|distinct',
            // Keep singular filters for compatibility with existing bookmarks.
            'program_id' => 'nullable|integer|exists:programs,id',
            'status' => ['nullable', 'string', Rule::in(self::STUDENT_STATUSES)],
            'program_ids' => 'nullable|array|max:100',
            'program_ids.*' => 'required|integer|distinct|exists:programs,id',
            'specialization_ids' => 'nullable|array|max:100',
            'specialization_ids.*' => 'required|integer|distinct|exists:specializations,id',
            'statuses' => 'nullable|array|max:20',
            'statuses.*' => ['required', 'string', 'distinct', Rule::in(self::STUDENT_STATUSES)],
            'intake_semester_ids' => 'nullable|array|max:100',
            'intake_semester_ids.*' => 'required|integer|distinct|exists:semesters,id',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeStudentFilters(array $validated): array
    {
        return [
            'search' => $validated['search'] ?? '',
            'student_ids' => $validated['student_ids'] ?? [],
            'program_ids' => array_map('intval', $validated['program_ids'] ?? (isset($validated['program_id']) ? [$validated['program_id']] : [])),
            'specialization_ids' => array_map('intval', $validated['specialization_ids'] ?? []),
            'statuses' => $validated['statuses'] ?? (isset($validated['status']) ? [$validated['status']] : []),
            'intake_semester_ids' => array_map('intval', $validated['intake_semester_ids'] ?? []),
        ];
    }
}
