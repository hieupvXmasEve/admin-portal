<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Api\GoldTransactionController;
use App\Http\Controllers\Api\StudentWalletController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\GetRegistrationsRequest;
use App\Models\Student;
use App\Modules\Academic\Exports\StudentAcademicSummaryExport;
use App\Modules\Academic\Progression\Actions\AttachDecisionToTransitionAction;
use App\Modules\Academic\Progression\Queries\GetStudentGraduationProgressQuery;
use App\Modules\Academic\Progression\Queries\GetStudentRegistrationsQuery;
use App\Modules\Academic\Queries\GetStudentAttendanceDetailsQuery;
use App\Modules\Academic\Queries\GetStudentAttendanceQuery;
use App\Modules\Academic\Queries\GetStudentLifecycleTimelineQuery;
use App\Modules\Academic\Support\LifecycleFormOptions;
use App\Services\ExcelExportService;
use App\Services\StudentAcademicSummaryService;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Finance\HubStudentFinanceSummaryReader;
use App\Shared\Contracts\Finance\StudentFeeSummaryReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        private StudentGuardianRelationshipReader $guardianRelationshipReader,
        private GuardianAccessGrantReader $guardianAccessGrantReader,
        private ProgramEnrollmentReader $programEnrollmentReader,
    ) {
        $this->middleware('can:view_student_summary')->only([
            'show',
            'overview',
            'registrations',
            'scores',
            'attendance',
            'graduation',
            'lifecycle',
            'gold',
            'finance',
            'export',
        ]);

        // Backfilling an authorizing Decision is an act-capable operation.
        $this->middleware('can:change_student_status')->only(['attachDecision']);
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
        $programEnrollment = $this->programEnrollmentReader->forStudentId((int) $student->id);

        $student->loadMissing([
            'campus:id,name,code',
        ]);

        return [
            'id' => $student->id,
            'student_id' => $student->student_id,
            'full_name' => $student->full_name,
            'status' => $programEnrollment->legacyCompatibleStatus(),
            'email' => $student->email,
            'intake' => $student->intake,
            'avatar_url' => $student->avatar_url,
            'campus' => $student->campus ? [
                'id' => $student->campus->id,
                'name' => $student->campus->name,
                'code' => $student->campus->code,
            ] : null,
            'program' => $programEnrollment->programPayload(),
            'specialization' => $programEnrollment->specializationPayload(),
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

        if ($canAct) {
            $relationships = $this->guardianRelationshipReader->forStudent((int) $student->id);
            $activeRelationshipIds = array_flip($this->guardianAccessGrantReader->activeRelationshipIds(
                array_map(static fn ($relationship): int => $relationship->id, $relationships),
            ));
            $overviewData['student_info']['guardians'] = array_map(
                static fn ($relationship): array => [
                    ...$relationship->toArray(),
                    'can_receive_access' => $relationship->email !== null && trim($relationship->email) !== '',
                    'has_active_access' => isset($activeRelationshipIds[$relationship->id]),
                ],
                $relationships,
            );
        }

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
    public function registrations(Student $student, GetRegistrationsRequest $request, GetStudentRegistrationsQuery $query): Response
    {
        $validated = $request->validated();
        $registrationsData = $query->handle((int) $student->id, $validated);

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
    public function graduation(Student $student, GetStudentGraduationProgressQuery $query): Response
    {
        $graduationData = $query->handle(
            (int) $student->id,
            $student->expected_graduation_date?->toDateString(),
        );

        return Inertia::render('students/AcademicSummary/Graduation', [
            'student' => $this->hubStudentContext($student),
            'graduation' => $graduationData,
        ]);
    }

    /**
     * Display the Lifecycle tab: one chronological timeline merged from Student
     * Actions and EGC Academic Progression, with authorizing Decisions inline
     * and EGC level / IELTS detail in a sub-panel (ADR-0009). Status changes and
     * EGC placement/progression are recorded in place here, so the standalone
     * actions and placement pages redirect into this tab.
     *
     * @return Response Inertia response with the merged timeline and act payloads
     */
    public function lifecycle(
        Student $student,
        Request $request,
        GetStudentLifecycleTimelineQuery $query,
        LifecycleFormOptions $options,
    ): Response {
        $lifecycle = $query->handle($student);

        $user = $request->user();
        $canChangeStatus = $user?->can('change_student_status') ?? false;
        $canViewActions = $user?->can('view_student_action') ?? false;

        return Inertia::render('students/AcademicSummary/Lifecycle', [
            'student' => $this->hubStudentContext($student),
            'timeline' => $lifecycle['timeline'],
            'egc' => $lifecycle['egc'],
            'can_act' => $canChangeStatus || $canViewActions,
            'can_change_status' => $canChangeStatus,
            'options' => [
                'action' => $options->actionOptions($student),
                'placement' => $options->placementOptions(),
                'decisions' => $options->decisionOptions(),
            ],
        ]);
    }

    /**
     * Backfill an authorizing Decision onto an existing lifecycle transition
     * (ADR-0008). The transition was recordable without a Decision; attaching one
     * later clears its missing-decision flag and adds the student to the
     * Decision's coverage roster.
     */
    public function attachDecision(Student $student, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:action,progression'],
            'source_id' => ['required', 'integer'],
            'decision_id' => ['required', 'integer', 'exists:student_decisions,id'],
        ]);

        AttachDecisionToTransitionAction::run([
            'student_id' => $student->id,
            'source' => $validated['source'],
            'source_id' => (int) $validated['source_id'],
            'decision_id' => (int) $validated['decision_id'],
        ]);

        return back()->with('success', 'Decision attached to the transition.');
    }

    /**
     * Download a single-student academic-summary workbook.
     *
     * Composes the academic summary the Hub shows — identity, cumulative GPA
     * and standing, graduation progress, and a transcript of finalized
     * academic records — into a downloadable Excel file. This replaces the
     * Hub context-bar's former "coming soon" Export placeholder.
     *
     * @param  Student  $student  The student to export the academic summary for
     * @return BinaryFileResponse The downloadable xlsx response
     */
    public function export(Student $student, ExcelExportService $excelService): BinaryFileResponse
    {
        $data = $this->academicSummaryService->getAcademicSummaryExportData($student);

        $export = new StudentAcademicSummaryExport(
            student: $data['student'],
            graduation: $data['graduation'],
            cumulative: $data['cumulative'],
            courses: $data['courses'],
        );

        $filename = 'academic_summary_'.$student->student_id.'_'.now()->format('Y-m-d_H-i-s');

        return $excelService->download($export, $filename);
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
    public function fees(Student $student, StudentFeeSummaryReader $reader): Response
    {
        $feeSummary = $reader->execute((int) $student->id);

        return Inertia::render('students/AcademicSummary/Fee', [
            'student' => $this->hubStudentContext($student),
            'feeSummary' => $feeSummary,
        ]);
    }

    /**
     * Display the read-only Finance tab for the Hub.
     *
     * The Hub is finance-aware but not finance-owning (ADR-0007): fees, gold,
     * and scholarships are read through the Finance module's cross-module read
     * contract — never by joining into Finance tables — and surfaced as a
     * summary only. Any money operation deep-links out to the Finance Office,
     * which owns mutation. No mutation path exists on this tab.
     *
     * @param  Student  $student  The student to display the finance summary for
     * @return Response Inertia response with the read-only summary and deep link
     */
    public function finance(Student $student, HubStudentFinanceSummaryReader $reader): Response
    {
        return Inertia::render('students/AcademicSummary/Finance', [
            'student' => $this->hubStudentContext($student),
            'finance' => $reader->summary($student->id),
            // Deep link into the Finance Office surface that owns money operations.
            'financeOfficeUrl' => route('finance.students.overview', $student->id),
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
