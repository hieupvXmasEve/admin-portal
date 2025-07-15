<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\Program;
use App\Services\AcademicRecordService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AcademicRecordController extends Controller
{
    public function __construct(
        private AcademicRecordService $academicRecordService
    ) {}

    /**
     * Display global academic records overview (for menu access)
     */
    public function globalIndex(Request $request): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');

        if (!$campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'semester_id' => 'nullable|exists:semesters,id',
            'program_id' => 'nullable|exists:programs,id',
        ]);

        // Get students from the current campus
        $studentsQuery = Student::with(['program', 'specialization', 'academicRecords'])
            ->where('campus_id', $campusId);

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $studentsQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['program_id'])) {
            $studentsQuery->where('program_id', $validated['program_id']);
        }

        $students = $studentsQuery->orderBy('created_at', 'desc')->paginate(15);

        $programs = Program::get();
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('students/academic-records/GlobalIndex', [
            'students' => $students,
            'programs' => $programs,
            'semesters' => $semesters,
            'filters' => $validated,
        ]);
    }

    /**
     * Display academic records for a student
     */
    public function index(Student $student, Request $request): Response
    {
        $this->authorize('view', $student);

        $validated = $request->validate([
            'semester_id' => 'nullable|exists:semesters,id',
            'unit_id' => 'nullable|exists:units,id',
            'status' => 'nullable|string',
        ]);

        $records = $this->academicRecordService->getStudentRecords($student, $validated);
        $semesters = Semester::orderBy('start_date', 'desc')->get();
        $performanceAnalytics = $this->academicRecordService->getPerformanceAnalytics($student);

        return Inertia::render('students/academic-records/Index', [
            'student' => $student->load(['campus', 'program', 'specialization']),
            'records' => $records,
            'semesters' => $semesters,
            'analytics' => $performanceAnalytics,
            'filters' => $validated,
        ]);
    }

    /**
     * Show detailed academic record
     */
    public function show(Student $student, int $recordId, Request $request): Response|JsonResponse
    {
        $this->authorize('view', $student);

        $record = $student->academicRecords()
            ->with(['unit', 'semester'])
            ->findOrFail($recordId);

        if ($request->expectsJson()) {
            return response()->json(['record' => $record]);
        }

        return Inertia::render('students/academic-records/Show', [
            'student' => $student->load(['campus', 'program', 'specialization']),
            'record' => $record,
        ]);
    }

    /**
     * Generate transcript for student
     */
    public function transcript(Student $student): Response
    {
        $this->authorize('view', $student);

        $transcriptData = $this->academicRecordService->generateTranscript($student);

        return Inertia::render('students/academic-records/Transcript', $transcriptData);
    }

    /**
     * Show GPA history
     */
    public function gpaHistory(Student $student): Response
    {
        $this->authorize('view', $student);

        $performanceAnalytics = $this->academicRecordService->getPerformanceAnalytics($student);

        return Inertia::render('students/academic-records/GpaHistory', [
            'student' => $student->load(['campus', 'program', 'specialization']),
            'analytics' => $performanceAnalytics,
        ]);
    }

    /**
     * Create or update academic record
     */
    public function store(Student $student, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $student);

        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_id' => 'required|exists:units,id',
            'final_grade' => 'required|string|max:10',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        try {
            $record = $this->academicRecordService->createOrUpdateRecord($student, $validated);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Academic record updated successfully',
                    'record' => $record,
                ]);
            }

            return redirect()
                ->route('students.academic-records.index', $student)
                ->with('success', 'Academic record updated successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to update academic record',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update academic record: ' . $e->getMessage());
        }
    }

    /**
     * Get grade distribution for a unit
     */
    public function gradeDistribution(Unit $unit, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => 'nullable|exists:semesters,id',
        ]);

        $semester = $validated['semester_id'] ? Semester::find($validated['semester_id']) : null;
        $distribution = $this->academicRecordService->getGradeDistribution($unit, $semester);

        return response()->json($distribution);
    }
}
