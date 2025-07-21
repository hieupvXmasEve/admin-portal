<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentAcademicSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for handling Student Academic Summary functionality
 *
 * Provides comprehensive academic overview including:
 * - Course registrations and outcomes
 * - Assessment scores and performance
 * - Attendance records and patterns
 * - GPA calculations and academic standing
 * - Graduation progress tracking
 */
class StudentAcademicSummaryController extends Controller
{
    public function __construct(
        private StudentAcademicSummaryService $academicSummaryService
    ) {
        $this->middleware('can:view_student_summary')->only(['show']);
    }

    /**
     * Display the academic summary for a specific student
     *
     * @param Student $student The student to display summary for
     * @return Response Inertia response with academic summary data
     */
    public function show(Student $student): Response
    {
        // Additional authorization check for the specific student
        $this->authorize('view_student_summary', $student);

        // Get comprehensive academic summary data
        $academicSummary = $this->academicSummaryService->getAcademicSummary($student->id);

        return Inertia::render('students/AcademicSummary', [
            'student' => $student->load([
                'campus:id,name,code',
                'program:id,name,code',
                'specialization:id,name,code',
                'curriculumVersion:id,version_code,program_id,specialization_id',
                'curriculumVersion.program:id,name,code',
                'curriculumVersion.specialization:id,name,code',
            ]),
            'academicSummary' => $academicSummary,
        ]);
    }

    /**
     * Filter academic data by semester
     *
     * @param Student $student The student to filter data for
     * @param Request $request Request containing semester filter
     * @return JsonResponse Filtered academic data
     */
    public function filterBySemester(Student $student, Request $request): JsonResponse
    {
        $this->authorize('view_student_summary', $student);

        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $filteredData = $this->academicSummaryService->filterBySemester(
            $student->id,
            $validated['semester_id']
        );

        return response()->json([
            'success' => true,
            'data' => $filteredData,
        ]);
    }

    /**
     * Filter academic data by course offering
     *
     * @param Student $student The student to filter data for
     * @param Request $request Request containing course offering filter
     * @return JsonResponse Filtered academic data
     */
    public function filterByCourseOffering(Student $student, Request $request): JsonResponse
    {
        $this->authorize('view_student_summary', $student);

        $validated = $request->validate([
            'course_offering_id' => 'required|exists:course_offerings,id',
        ]);

        $filteredData = $this->academicSummaryService->filterByCourseOffering(
            $student->id,
            $validated['course_offering_id']
        );

        return response()->json([
            'success' => true,
            'data' => $filteredData,
        ]);
    }

    /**
     * Get attendance details for a specific unit
     *
     * @param Student $student The student to get attendance for
     * @param Request $request Request containing unit filter
     * @return JsonResponse Detailed attendance data
     */
    public function getAttendanceDetails(Student $student, Request $request): JsonResponse
    {
        $this->authorize('view_student_summary', $student);

        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'nullable|exists:semesters,id',
        ]);

        $attendanceDetails = $this->academicSummaryService->getAttendanceDetails(
            $student->id,
            $validated['unit_id'],
            $validated['semester_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $attendanceDetails,
        ]);
    }

    /**
     * Get detailed scores for a specific course offering
     *
     * @param Student $student The student to get scores for
     * @param Request $request Request containing course offering filter
     * @return JsonResponse Detailed score breakdown
     */
    public function getScoreDetails(Student $student, Request $request): JsonResponse
    {
        $this->authorize('view_student_summary', $student);

        $validated = $request->validate([
            'course_offering_id' => 'required|exists:course_offerings,id',
        ]);

        $scoreDetails = $this->academicSummaryService->getScoreDetails(
            $student->id,
            $validated['course_offering_id']
        );

        return response()->json([
            'success' => true,
            'data' => $scoreDetails,
        ]);
    }

    /**
     * Get paginated scores for a specific course offering (for lazy loading)
     *
     * @param Student $student The student to get scores for
     * @param int $courseOfferingId The course offering ID
     * @param Request $request Request containing pagination parameters
     * @return JsonResponse Paginated scores data
     */
    public function getCourseScores(Student $student, int $courseOfferingId, Request $request): JsonResponse
    {
        $this->authorize('view_student_summary', $student);

        $validated = $request->validate([
            'offset' => 'integer|min:0',
            'limit' => 'integer|min:1|max:100',
        ]);

        $offset = $validated['offset'] ?? 0;
        $limit = $validated['limit'] ?? 20;

        $scoresData = $this->academicSummaryService->getCourseScoresDetails(
            $student,
            $courseOfferingId,
            $offset,
            $limit
        );

        return response()->json([
            'success' => true,
            'data' => $scoresData,
        ]);
    }
}
