<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Actions\Student\GetStudentRegistrationsAction;
use App\Http\Controllers\Api\GoldTransactionController;
use App\Http\Controllers\Api\StudentWalletController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\GetRegistrationsRequest;
use App\Models\Student;
use App\Modules\Academic\Queries\GetStudentAttendanceDetailsQuery;
use App\Modules\Academic\Queries\GetStudentAttendanceQuery;
use App\Modules\Academic\Queries\GetStudentFeeSummaryQuery;
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
        private StudentAcademicSummaryService $academicSummaryService,
        private StudentWalletController $studentWalletController,
        private GoldTransactionController $goldTransactionController,
    ) {
        $this->middleware('can:view_student_summary')->only([
            'show',
            'overview',
            'registrations',
            'scores',
            'attendance',
            'graduation',
            'gold',
        ]);
    }

    /**
     * Build the persistent Hub context-bar payload shared by every tab.
     *
     * Keeps the context bar (photo, identity, status, intake, program /
     * specialization) consistent on all tabs without each method duplicating
     * the field list.
     *
     * @return array<string, mixed>
     */
    private function hubStudentContext(Student $student): array
    {
        $student->loadMissing([
            'campus:id,name,code',
            'program:id,name,code',
            'specialization:id,name,code',
        ]);

        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'full_name' => $student->full_name,
            'status' => $student->status,
            'email' => $student->email,
            'intake' => $student->intake,
            'avatar_url' => $student->avatar_url,
            'campus' => $student->campus ? [
                'id' => $student->campus->id,
                'name' => $student->campus->name,
                'code' => $student->campus->code,
            ] : null,
            'program' => $student->program ? [
                'id' => $student->program->id,
                'name' => $student->program->name,
                'code' => $student->program->code,
            ] : null,
            'specialization' => $student->specialization ? [
                'id' => $student->specialization->id,
                'name' => $student->specialization->name,
                'code' => $student->specialization->code,
            ] : null,
        ];
    }

    /**
     * Whether the current viewer is an act-capable academic officer.
     *
     * Drives full-vs-reduced Hub data and act-control visibility (ADR-0007).
     */
    private function canActOnStudent(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        return $user->can('change_student_status') || $user->can('view_student_action');
    }

    /**
     * Display the overview tab for academic summary
     *
     * @param  Student  $student  The student to display overview for
     * @return Response Inertia response with overview data
     */
    public function overview(Student $student, Request $request): Response
    {

        // Load necessary relationships
        $student->load([
            'campus:id,name,code',
            'program:id,name,code',
            'specialization:id,name,code',
            'curriculumVersion:id,version_code,program_id,specialization_id',
            'curriculumVersion.program:id,name,code',
            'curriculumVersion.specialization:id,name,code',
            'intakeSemester:id,code,name',
            'scholarshipAward',
            'scholarshipAward.scholarshipDefinition:code,name,amount,type',
            'parentProfiles.user:id,name,email',
        ]);

        // Act-capable (Cán Bộ Đào tạo) staff see the full Hub; view-only roles
        // get a reduced read-only field set (ADR-0007).
        $canAct = $this->canActOnStudent($request);

        $overviewData = $this->academicSummaryService->getOverviewData($student, $canAct);

        return Inertia::render('students/AcademicSummary/Overview', [
            'student' => $this->hubStudentContext($student),
            'overview' => $overviewData,
            'can_act' => $canAct,
        ]);
    }

    /**
     * Display the registrations tab for academic summary
     *
     * @param  Student  $student  The student to display registrations for
     * @return Response Inertia response with registrations data
     */
    /**
     * Get registrations tab data
     */
    public function registrations(Student $student, GetRegistrationsRequest $request, GetStudentRegistrationsAction $action): Response
    {
        $validated = $request->validated();
        $registrationsData = $action->execute($student, $validated);

        return Inertia::render('students/AcademicSummary/Registrations', [
            'student' => $this->hubStudentContext($student),
            'registrations' => $registrationsData,
            'filters' => [
                'academic_year' => $validated['academic_year'] ?? null,
                'semester_id' => $validated['semester_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'is_retake' => $validated['is_retake'] ?? null,
                'per_page' => $validated['per_page'] ?? null,
                'page' => $validated['page'] ?? null,
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
            ],
        ]);
    }

    /**
     * Display the scores tab for academic summary
     *
     * @param  Student  $student  The student to display scores for
     * @return Response Inertia response with scores data
     */
    public function scores(Student $student): Response
    {

        $scoresData = $this->academicSummaryService->getScoresData($student);

        return Inertia::render('students/AcademicSummary/Scores', [
            'student' => $this->hubStudentContext($student),
            'scores' => $scoresData,
        ]);
    }

    /**
     * Display the attendance tab for academic summary
     *
     * @param  Student  $student  The student to display attendance for
     * @return Response Inertia response with attendance data
     */
    public function attendance(Student $student, GetStudentAttendanceQuery $query): Response
    {
        $attendanceData = $query->execute($student);

        return Inertia::render('students/AcademicSummary/Attendance', [
            'student' => $this->hubStudentContext($student),
            'attendance' => $attendanceData,
        ]);
    }

    /**
     * Display the graduation tab for academic summary
     *
     * @param  Student  $student  The student to display graduation for
     * @return Response Inertia response with graduation data
     */
    public function graduation(Student $student): Response
    {

        $graduationData = $this->academicSummaryService->getGraduationData($student);

        return Inertia::render('students/AcademicSummary/Graduation', [
            'student' => $this->hubStudentContext($student),
            'graduation' => $graduationData,
        ]);
    }

    /**
     * Display the gold tab for academic summary
     *
     * @param  Student  $student  The student to display gold for
     * @param  Request  $request  The request instance
     * @return Response Inertia response with gold data
     */
    public function gold(Student $student, Request $request): Response
    {

        // Get gold summary data
        $goldSummary = $this->studentWalletController->summaryForStudent($student);
        $walletData = $goldSummary->getData(true);

        // Get recent transactions
        $recentTransactions = $this->goldTransactionController->recentForStudent($student, $request);
        $recentTransactionsData = $recentTransactions->getData(true);

        // Get transaction stats
        $transactionStats = $this->goldTransactionController->statsForStudent($student);
        $transactionStatsData = $transactionStats->getData(true);

        return Inertia::render('students/AcademicSummary/Gold', [
            'student' => $this->hubStudentContext($student),
            'gold' => [
                'summary' => $walletData['data'] ?? $walletData,
                'recent_transactions' => $recentTransactionsData['data'] ?? $recentTransactionsData,
                'stats' => $transactionStatsData['data'] ?? $transactionStatsData,
            ],
        ]);
    }

    /**
     * Display the fees tab for academic summary
     *
     * @param  Student  $student  The student to display fees for
     * @return Response Inertia response with fees data
     */
    public function fees(Student $student, GetStudentFeeSummaryQuery $query): Response
    {
        $feeSummary = $query->execute($student);

        return Inertia::render('students/AcademicSummary/Fee', [
            'student' => $this->hubStudentContext($student),
            'feeSummary' => $feeSummary,
        ]);
    }

    /**
     * Get attendance details for a specific unit
     *
     * @param  Student  $student  The student to get attendance for
     * @param  Request  $request  Request containing unit filter
     * @return JsonResponse Detailed attendance data
     */
    public function getAttendanceDetails(Student $student, Request $request, GetStudentAttendanceDetailsQuery $query): JsonResponse
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'course_offering_id' => 'nullable|exists:course_offerings,id',
        ]);

        $attendanceDetails = $query->execute(
            $student->id,
            (int) $validated['unit_id'],
            isset($validated['semester_id']) ? (int) $validated['semester_id'] : null,
            isset($validated['course_offering_id']) ? (int) $validated['course_offering_id'] : null
        );

        return response()->json([
            'success' => true,
            'data' => $attendanceDetails,
        ]);
    }

    /**
     * Get detailed scores for a specific course offering
     *
     * @param  Student  $student  The student to get scores for
     * @param  Request  $request  Request containing course offering filter
     * @return JsonResponse Detailed score breakdown
     */
    public function getScoreDetails(Student $student, Request $request): JsonResponse
    {

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
     * @param  Student  $student  The student to get scores for
     * @param  int  $courseOfferingId  The course offering ID
     * @param  Request  $request  Request containing pagination parameters
     * @return JsonResponse Paginated scores data
     */
    public function getCourseScores(Student $student, int $courseOfferingId, Request $request): JsonResponse
    {

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
