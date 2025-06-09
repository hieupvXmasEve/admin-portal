<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use App\Services\StudentManagementService;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(
        private StudentManagementService $studentService
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'program_id' => 'nullable|integer|exists:programs,id',
            'enrollment_status' => 'nullable|string|in:admitted,enrolled,active,on_leave,suspended,graduated,dropped_out',
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
            ->when($validated['enrollment_status'] ?? null, function ($query, $status) {
                $query->where('enrollment_status', $status);
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
                'enrollment_status' => $validated['enrollment_status'] ?? null,
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
                'per_page' => $validated['per_page'] ?? 15,
            ],
            'campuses' => $campuses,
            'programs' => $programs,
            'statistics' => $statistics,
        ]);
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

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        try {
            $student = $this->studentService->createStudent($request->validated());

            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Student created successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create student', [
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(Student $student): Response
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

        // Get academic statistics
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

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        try {
            $updatedStudent = $this->studentService->updateStudent($student, $request->validated());

            return redirect()
                ->route('students.show', $updatedStudent)
                ->with('success', 'Student updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update student', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Student $student): RedirectResponse
    {
        try {
            DB::transaction(function () use ($student) {
                // Check if student has active registrations
                $activeRegistrations = $student->courseRegistrations()->active()->count();
                if ($activeRegistrations > 0) {
                    throw new \Exception('Cannot delete student with active course registrations');
                }

                // Soft delete the student
                $student->delete();
            });

            return redirect()
                ->route('students.index')
                ->with('success', 'Student deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete student', [
                'student_id' => $student->id,
                'error' => $e->getMessage()
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
     * Update enrollment status
     */
    public function updateEnrollmentStatus(Request $request, Student $student): RedirectResponse
    {
        $validated = $request->validate([
            'enrollment_status' => 'required|in:admitted,enrolled,active,on_leave,suspended,graduated,dropped_out',
        ]);

        try {
            $updatedStudent = $this->studentService->updateEnrollmentStatus(
                $student,
                $validated['enrollment_status']
            );

            return redirect()
                ->route('students.show', $updatedStudent)
                ->with('success', 'Enrollment status updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update student enrollment status', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
                'status' => $validated['enrollment_status']
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
}
