<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Services\AcademicRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicRecordController extends Controller
{
    public function __construct(
        private AcademicRecordService $academicRecordService
    ) {}

    /**
     * Display academic records for a student
     */
    public function index(Student $student, Request $request): Response
    {

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

        $transcriptData = $this->academicRecordService->generateTranscript($student);

        return Inertia::render('students/academic-records/Transcript', $transcriptData);
    }

    /**
     * Show GPA history
     */
    public function gpaHistory(Student $student): Response
    {

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

        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_id' => 'required|exists:units,id',
            'final_letter_grade' => 'required|string|max:10',
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
