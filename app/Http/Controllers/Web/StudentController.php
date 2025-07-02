<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use App\Services\StudentService;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\Student\StudentResource;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(
        private StudentService $studentService
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'program_id' => 'nullable|integer|exists:programs,id',
            'status' => 'nullable|string|in:active,inactive,suspended,graduated',
            'sort' => 'nullable|string|in:student_id,full_name,email,admission_date,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $students = Student::query()
            ->with(['campus', 'program', 'specialization'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('student_id', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($validated['campus_id'] ?? null, function ($query, $campusId) {
                $query->where('campus_id', $campusId);
            })
            ->when($validated['program_id'] ?? null, function ($query, $programId) {
                $query->where('program_id', $programId);
            })
            ->when($validated['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        // Get filter options
        $campuses = Campus::orderBy('name')->get(['id', 'name']);
        $programs = Program::orderBy('name')->get(['id', 'name']);

        // Get statistics
        $statistics = $this->studentService->getStudentStatistics($validated['campus_id'] ?? null);

        return Inertia::render('students/Index', [
            'students' => $students,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'campus_id' => $validated['campus_id'] ?? null,
                'program_id' => $validated['program_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
                'per_page' => $validated['per_page'] ?? 15,
            ],
            'campuses' => $campuses,
            'programs' => $programs,
            'statistics' => $statistics,
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

    public function create(): Response
    {
        $campuses = Campus::orderBy('name')->get(['id', 'name', 'code']);
        $programs = Program::with('specializations')->orderBy('name')->get();

        return Inertia::render('students/Create', [
            'campuses' => $campuses,
            'programs' => $programs,
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse|StudentResource|JsonResponse
    {
        try {
            $student = $this->studentService->createStudent($request->validated());

            // Return JSON response for API requests
            if ($request->expectsJson()) {
                return new StudentResource($student);
            }

            // Return redirect for web requests
            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Student created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create student', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage()
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
            }
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
                ->route('students.show', $updatedStudent)
                ->with('success', 'Student updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Student $student, Request $request): RedirectResponse|\Illuminate\Http\Response|JsonResponse
    {
        try {
            DB::transaction(function () use ($student) {
                // Check if student has active registrations
                $activeRegistrations = $student->courseRegistrations()->active()->count();
                if ($activeRegistrations > 0) {
                    throw new \Exception('Cannot delete student with active course registrations');
                }

                // Use service to delete the student
                $this->studentService->deleteStudent($student);
            });

            // Return JSON response for API requests
            if ($request->expectsJson()) {
                return response()->noContent();
            }

            // Return redirect for web requests
            return redirect()
                ->route('students.index')
                ->with('success', 'Student deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete student', [
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $e->getMessage()
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
            'status' => 'required|in:active,inactive,suspended,graduated',
        ]);

        try {
            $updatedStudent = $this->studentService->updateStudentStatus(
                $student,
                $validated['status']
            );

            return redirect()
                ->route('students.show', $updatedStudent)
                ->with('success', 'Student status updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update student status', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'status' => $validated['status']
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Assign program to student
     */
    public function assignProgram(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'specialization_id' => 'nullable|exists:specializations,id',
            'curriculum_version_id' => 'nullable|exists:curriculum_versions,id',
        ]);

        try {
            $updatedStudent = $this->studentService->assignProgram(
                $student,
                $validated['program_id'],
                $validated['specialization_id'] ?? null,
                $validated['curriculum_version_id'] ?? null
            );

            return redirect()
                ->route('students.show', $updatedStudent)
                ->with('success', 'Program assigned successfully');
        } catch (\Exception $e) {
            Log::error('Failed to assign program to student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
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
}
