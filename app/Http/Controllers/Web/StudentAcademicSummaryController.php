<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\GoldTransactionController;
use App\Http\Controllers\Api\StudentWalletController;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentAcademicSummaryService;
use App\Services\CashWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        private CashWalletService $walletService
    ) {
        $this->middleware('can:view_student_summary')->only([
            'show',
            'overview',
            'registrations',
            'scores',
            'attendance',
            'gpa',
            'graduation',
            'gold',
            'wallet',
        ]);
    }

    /**
     * Display the academic summary for a specific student
     *
     * @param  Student  $student  The student to display summary for
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
     * Display the overview tab for academic summary
     *
     * @param  Student  $student  The student to display overview for
     * @return Response Inertia response with overview data
     */
    public function overview(Student $student): Response
    {
        $this->authorize('view_student_summary', $student);

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
        ]);

        $overviewData = $this->academicSummaryService->getOverviewData($student);

        return Inertia::render('students/AcademicSummary/Overview', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
            'overview' => $overviewData,
        ]);
    }

    /**
     * Display the registrations tab for academic summary
     *
     * @param  Student  $student  The student to display registrations for
     * @return Response Inertia response with registrations data
     */
    public function registrations(Student $student): Response
    {
        $this->authorize('view_student_summary', $student);

        // Load necessary relationships
        $student->load([
            'campus:id,name,code',
            'program:id,name,code',
            'specialization:id,name,code',
        ]);

        $registrationsData = $this->academicSummaryService->getRegistrationsData($student);

        return Inertia::render('students/AcademicSummary/Registrations', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
            'registrations' => $registrationsData,
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
        $this->authorize('view_student_summary', $student);

        $scoresData = $this->academicSummaryService->getScoresData($student);

        return Inertia::render('students/AcademicSummary/Scores', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
            'scores' => $scoresData,
        ]);
    }

    /**
     * Display the attendance tab for academic summary
     *
     * @param  Student  $student  The student to display attendance for
     * @return Response Inertia response with attendance data
     */
    public function attendance(Student $student): Response
    {
        $this->authorize('view_student_summary', $student);

        $attendanceData = $this->academicSummaryService->getAttendanceData($student);

        return Inertia::render('students/AcademicSummary/Attendance', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
            'attendance' => $attendanceData,
        ]);
    }

    /**
     * Display the GPA tab for academic summary
     *
     * @param  Student  $student  The student to display GPA for
     * @return Response Inertia response with GPA data
     */
    public function gpa(Student $student): Response
    {
        $this->authorize('view_student_summary', $student);

        $gpaData = $this->academicSummaryService->getGpaData($student);

        return Inertia::render('students/AcademicSummary/Gpa', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
            'gpa' => $gpaData,
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
        $this->authorize('view_student_summary', $student);

        $graduationData = $this->academicSummaryService->getGraduationData($student);

        return Inertia::render('students/AcademicSummary/Graduation', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
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
        $this->authorize('view_student_summary', $student);

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
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
            'gold' => [
                'summary' => $walletData['data'] ?? $walletData,
                'recent_transactions' => $recentTransactionsData['data'] ?? $recentTransactionsData,
                'stats' => $transactionStatsData['data'] ?? $transactionStatsData,
            ],
        ]);
    }

    /**
     * Display the wallet tab for academic summary
     *
     * @param  Student  $student  The student to display wallet for
     * @return Response Inertia response with wallet data
     */
    public function wallet(Student $student): Response
    {
        // Get or create wallet for the student
        $wallet = $this->walletService->getOrCreateWallet($student->id);

        // Get transaction history with pagination
        $transactions = $this->walletService->getTransactionHistory($wallet->id, 20);

        // Get wallet statistics
        $stats = $this->walletService->getWalletStats($wallet->id);

        // Get tuition plan for the student
        $tuitionPlan = \App\Models\TuitionPlan::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $student->intake_semester_id)
            ->where('is_active', true)
            ->with([
                'curriculumVersion:id,version_code,program_id,specialization_id',
                'curriculumVersion.program:id,name,code',
                'curriculumVersion.specialization:id,name,code',
                'intakeSemester:id,code,name,start_date,end_date',
                'terms' => function ($query) {
                    $query->with('semester:id,code,name,start_date,end_date')
                        ->orderBy('term_number');
                },
            ])
            ->first();

        return Inertia::render('students/AcademicSummary/Wallets/Show', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
            'wallet' => [
                'id' => $wallet->id,
                'balance' => $wallet->balance,
                'currency' => $wallet->currency,
                'formatted_balance' => $wallet->formatted_balance,
            ],
            'transactions' => $transactions,
            'stats' => $stats,
            'tuitionPlan' => $tuitionPlan ? [
                'id' => $tuitionPlan->id,
                'total_amount' => $tuitionPlan->total_amount,
                'currency' => $tuitionPlan->currency,
                'is_active' => $tuitionPlan->is_active,
                'curriculum_version' => $tuitionPlan->curriculumVersion ? [
                    'id' => $tuitionPlan->curriculumVersion->id,
                    'version_code' => $tuitionPlan->curriculumVersion->version_code,
                    'program' => $tuitionPlan->curriculumVersion->program,
                    'specialization' => $tuitionPlan->curriculumVersion->specialization,
                ] : null,
                'intake_semester' => $tuitionPlan->intakeSemester,
                'terms' => $tuitionPlan->terms->map(fn($term) => [
                    'id' => $term->id,
                    'term_number' => $term->term_number,
                    'amount' => $term->amount,
                    'due_date' => $term->due_date?->format('Y-m-d'),
                    'formatted_due_date' => $term->due_date?->format('d/m/Y'),
                    'semester' => $term->semester,
                ]),
            ] : null,
        ]);
    }

    /**
     * Filter academic data by semester
     *
     * @param  Student  $student  The student to filter data for
     * @param  Request  $request  Request containing semester filter
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
     * @param  Student  $student  The student to filter data for
     * @param  Request  $request  Request containing course offering filter
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
     * @param  Student  $student  The student to get attendance for
     * @param  Request  $request  Request containing unit filter
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
     * @param  Student  $student  The student to get scores for
     * @param  Request  $request  Request containing course offering filter
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
     * @param  Student  $student  The student to get scores for
     * @param  int  $courseOfferingId  The course offering ID
     * @param  Request  $request  Request containing pagination parameters
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
