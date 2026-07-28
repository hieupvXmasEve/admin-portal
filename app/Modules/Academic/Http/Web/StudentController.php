<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Constants\StudentRoutes;
use App\Exports\StudentExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Academic\Catalog\Queries\GetStudentDirectoryFormOptionsQuery;
use App\Modules\Academic\Http\Requests\Student\ExportStudentsRequest;
use App\Modules\Academic\Http\Requests\Student\GetStudentsByCodesRequest;
use App\Modules\Academic\Http\Requests\Student\ListStudentsRequest;
use App\Modules\Academic\Http\Requests\Student\SearchStudentsRequest;
use App\Modules\Academic\Queries\ExportStudentsQuery;
use App\Modules\Academic\Queries\ListStudentsQuery;
use App\Services\StudentService;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use App\Shared\Contracts\StudentRegistry\DTO\GuardianRelationship;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentController extends Controller
{
    public function __construct(
        private StudentService $studentService,
        private StudentGuardianRelationshipReader $guardianRelationshipReader,
        private GuardianAccessGrantReader $guardianAccessGrantReader,
        private GetStudentDirectoryFormOptionsQuery $formOptions,
        private CampusReferenceReader $campuses,
    ) {}

    public function index(ListStudentsRequest $request, ListStudentsQuery $listStudentsQuery): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');
        Log::info('Current campus ID: '.$campusId);
        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validated();
        $filters = [
            ...$request->normalizedStudentFilters(),
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'per_page' => (int) ($validated['per_page'] ?? 15),
            'page' => (int) ($validated['page'] ?? 1),
        ];

        $students = $listStudentsQuery->handle($filters, (int) $campusId);

        ['programs' => $programs, 'specializations' => $specializations, 'intake_semesters' => $intakeSemesters] =
            $this->formOptions->filterOptions();

        // Get statistics for current campus
        $statistics = $this->studentService->getStudentStatistics($campusId);

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => $filters,
            'programs' => $programs,
            'specializations' => $specializations,
            'intake_semesters' => $intakeSemesters,
            'statistics' => $statistics,
            'current_campus_id' => $campusId,
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');

        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        return Inertia::render('Students/Create', [
            ...$this->formOptions->createOptions(),
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
                return ApiResponse::compatible([
                    'error' => $e->getMessage(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit(Student $student): Response
    {
        $student->load(['campus', 'program', 'specialization']);

        $campuses = array_map(
            static fn (CampusReference $campus): array => $campus->toArray(),
            $this->campuses->all(),
        );
        ['programs' => $programs, 'curriculumVersions' => $curriculumVersions] = $this->formOptions->editOptions(
            $student->program_id === null ? null : (int) $student->program_id,
            $student->specialization_id === null ? null : (int) $student->specialization_id,
        );

        $primaryGuardian = collect($this->guardianRelationshipReader->forStudent((int) $student->id))
            ->first(static fn (GuardianRelationship $relationship): bool => $relationship->isPrimary);
        $parentAccount = $primaryGuardian === null
            ? $this->guardianAccessGrantReader->primaryAccountForStudent((int) $student->id)
            : $this->guardianAccessGrantReader->activeAccountForRelationship($primaryGuardian->id);
        $student->setAttribute('parent_user', $parentAccount?->toArray());

        return Inertia::render('Students/Edit', [
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
        return Inertia::render('Students/PhotoCapture', [
            'studentId' => $student->id,
            'returnUrl' => route(StudentRoutes::EDIT, $student),
        ]);
    }

    /**
     * Search students (API endpoint)
     */
    public function apiSearch(SearchStudentsRequest $request): JsonResponse
    {
        $validated = $request->validated();

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

        return ApiResponse::compatible([
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
    public function apiShow(Student $student): JsonResponse
    {
        $student->load(['campus', 'program', 'specialization']);

        return ApiResponse::compatible([
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
    public function getByStudentIds(GetStudentsByCodesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $campusId = session()->get('current_campus_id');

        if (! $campusId) {
            return ApiResponse::compatible([
                'success' => false,
                'message' => 'No campus selected',
            ], 400);
        }

        $students = Student::with(['curriculumVersion', 'program', 'campus', 'specialization'])
            ->where('campus_id', $campusId)
            ->whereIn('student_id', $validated['student_ids'])
            ->get();

        return ApiResponse::compatible([
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
    public function export(ExportStudentsRequest $request, ExportStudentsQuery $exportQuery): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');

        if (! $campusId) {
            if ($request->expectsJson()) {
                return ApiResponse::compatible([
                    'success' => false,
                    'message' => 'Please select a campus first',
                ], 400);
            }

            return back()->withErrors(['error' => 'Please select a campus first']);
        }

        $validated = $request->validated();
        $filters = [
            'format' => $validated['format'],
            'scope' => $validated['scope'],
            ...$request->normalizedStudentFilters(),
        ];

        try {
            $query = $exportQuery->getBuilder($campusId, $filters);

            // Get campus info for filename
            $campus = $this->campuses->find((int) $campusId);
            $campusCode = $campus?->code ?? '';
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "students_{$campusCode}_{$timestamp}.{$validated['format']}";

            // Create export instance
            $export = new StudentExport($query, $filters);

            // Log the export action
            Log::info('Student export initiated', [
                'campus_id' => $campusId,
                'campus_code' => $campusCode,
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
                return ApiResponse::compatible([
                    'success' => false,
                    'message' => 'Export failed: '.$e->getMessage(),
                ], 500);
            }

            return back()->withErrors(['error' => 'Export failed: '.$e->getMessage()]);
        }
    }
}
