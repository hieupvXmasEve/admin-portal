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
            'tuitionPlan',
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

        $overviewData = $this->academicSummaryService->getOverviewData($student);

        return Inertia::render('students/AcademicSummary/Overview', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email', 'intake']),
            'overview' => $overviewData,
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
    public function registrations(Student $student, \App\Http\Requests\Student\GetRegistrationsRequest $request, \App\Actions\Student\GetStudentRegistrationsAction $action): Response
    {
        $validated = $request->validated();
        $registrationsData = $action->execute($student, $validated);

        return Inertia::render('students/AcademicSummary/Registrations', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email', 'intake']),
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
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email', 'intake']),
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

        $attendanceData = $this->academicSummaryService->getAttendanceData($student);

        return Inertia::render('students/AcademicSummary/Attendance', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email', 'intake']),
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

        $gpaData = $this->academicSummaryService->getGpaData($student);

        return Inertia::render('students/AcademicSummary/Gpa', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email', 'intake']),
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

        $graduationData = $this->academicSummaryService->getGraduationData($student);

        return Inertia::render('students/AcademicSummary/Graduation', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email', 'intake']),
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
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email', 'intake']),
            'gold' => [
                'summary' => $walletData['data'] ?? $walletData,
                'recent_transactions' => $recentTransactionsData['data'] ?? $recentTransactionsData,
                'stats' => $transactionStatsData['data'] ?? $transactionStatsData,
            ],
        ]);
    }

    /**
     * Display the tuition plan tab for academic summary
     *
     * @param  Student  $student  The student to display tuition plan for
     * @return Response Inertia response with tuition plan data
     */
    public function tuitionPlan(Student $student): Response
    {

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

        // Get student scholarship award if exists
        $scholarshipAward = \App\Models\StudentScholarshipAward::where('student_id', $student->id)
            ->with(['scholarshipDefinition:id,code,name,description,type,amount,valid_from,valid_until,is_active'])
            ->first();

        // Format scholarship award data if exists
        $scholarshipAwardData = null;
        if ($scholarshipAward) {
            $scholarshipAwardData = [
                'id' => $scholarshipAward->id,
                'scholarship_code' => $scholarshipAward->scholarship_code,
                'awarded_at' => $scholarshipAward->awarded_at instanceof \Carbon\Carbon
                    ? $scholarshipAward->awarded_at->format('Y-m-d')
                    : ($scholarshipAward->awarded_at ? \Carbon\Carbon::parse($scholarshipAward->awarded_at)->format('Y-m-d') : null),
                'formatted_awarded_at' => $scholarshipAward->awarded_at instanceof \Carbon\Carbon
                    ? $scholarshipAward->awarded_at->format('d/m/Y')
                    : ($scholarshipAward->awarded_at ? \Carbon\Carbon::parse($scholarshipAward->awarded_at)->format('d/m/Y') : null),
                'notes' => $scholarshipAward->notes,
                'scholarship' => $scholarshipAward->scholarshipDefinition ? [
                    'id' => $scholarshipAward->scholarshipDefinition->id,
                    'code' => $scholarshipAward->scholarshipDefinition->code,
                    'name' => $scholarshipAward->scholarshipDefinition->name,
                    'description' => $scholarshipAward->scholarshipDefinition->description,
                    'type' => $scholarshipAward->scholarshipDefinition->type,
                    'amount' => $scholarshipAward->scholarshipDefinition->amount,
                    'valid_from' => $scholarshipAward->scholarshipDefinition->valid_from ? (\Carbon\Carbon::parse($scholarshipAward->scholarshipDefinition->valid_from)->format('Y-m-d')) : null,
                    'valid_until' => $scholarshipAward->scholarshipDefinition->valid_until ? (\Carbon\Carbon::parse($scholarshipAward->scholarshipDefinition->valid_until)->format('Y-m-d')) : null,
                    'formatted_valid_from' => $scholarshipAward->scholarshipDefinition->valid_from ? (\Carbon\Carbon::parse($scholarshipAward->scholarshipDefinition->valid_from)->format('d/m/Y')) : null,
                    'formatted_valid_until' => $scholarshipAward->scholarshipDefinition->valid_until ? (\Carbon\Carbon::parse($scholarshipAward->scholarshipDefinition->valid_until)->format('d/m/Y')) : null,
                    'is_active' => $scholarshipAward->scholarshipDefinition->is_active,
                    'is_valid' => $scholarshipAward->scholarshipDefinition->isValid(),
                ] : null,
            ];
        }

        if (!$tuitionPlan) {
            return Inertia::render('students/AcademicSummary/TuitionPlan', [
                'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
                'tuitionPlan' => null,
                'scholarshipAward' => $scholarshipAwardData,
            ]);
        }

        // Get all invoice items that reference tuition plan terms for this student
        // Also load invoice with discounts to calculate voucher discounts
        $invoiceItems = \App\Models\InvoiceItem::whereHas('invoice', function ($query) use ($student) {
            $query->where('student_id', $student->id);
        })
            ->where('reference_type', \App\Models\TuitionPlanTerm::class)
            ->whereIn('reference_id', $tuitionPlan->terms->pluck('id'))
            ->with([
                'invoice' => function ($query) {
                    $query->select('id', 'invoice_number', 'semester_id', 'due_date', 'status', 'subtotal', 'discount_total', 'total_amount', 'paid_amount')
                        ->with(['discounts' => function ($q) {
                            $q->select('id', 'invoice_id', 'discount_type', 'discount_source', 'description', 'amount', 'reference_id')
                                ->with('voucher:id,code,name,discount_type,discount_value');
                        }]);
                },
            ])
            ->get();

        // Group invoice items by term_id and calculate payment status with discounts
        $termPayments = [];
        foreach ($invoiceItems as $item) {
            $termId = $item->reference_id;
            $invoice = $item->invoice;

            // Calculate discount allocated to this item
            // Discount is allocated proportionally based on item's share of invoice subtotal
            $itemDiscount = 0;
            if ($invoice->subtotal > 0 && $invoice->discount_total > 0) {
                $itemRatio = $item->total_price / $invoice->subtotal;
                $itemDiscount = $invoice->discount_total * $itemRatio;
            }

            // Actual amount after discount
            $itemAmountAfterDiscount = max(0, $item->total_price - $itemDiscount);

            if (!isset($termPayments[$termId])) {
                $termPayments[$termId] = [
                    'total_amount' => 0,
                    'discount_amount' => 0,
                    'amount_after_discount' => 0,
                    'paid_amount' => 0,
                    'invoices' => [],
                ];
            }

            $termPayments[$termId]['total_amount'] += $item->total_price;
            $termPayments[$termId]['discount_amount'] += $itemDiscount;
            $termPayments[$termId]['amount_after_discount'] += $itemAmountAfterDiscount;
            $termPayments[$termId]['paid_amount'] += $item->paid_amount;

            // Collect voucher discounts for this invoice
            $voucherDiscounts = $invoice->discounts
                ->where('discount_type', 'voucher')
                ->map(function ($discount) {
                    return [
                        'code' => $discount->discount_source,
                        'name' => $discount->description,
                        'amount' => $discount->amount,
                        'voucher' => $discount->voucher ? [
                            'code' => $discount->voucher->code,
                            'name' => $discount->voucher->name,
                            'discount_type' => $discount->voucher->discount_type,
                            'discount_value' => $discount->voucher->discount_value,
                        ] : null,
                    ];
                })
                ->values()
                ->toArray();

            $termPayments[$termId]['invoices'][] = [
                'invoice_number' => $invoice->invoice_number,
                'semester_id' => $invoice->semester_id,
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'status' => $invoice->status,
                'item_total' => $item->total_price,
                'item_discount' => $itemDiscount,
                'item_amount_after_discount' => $itemAmountAfterDiscount,
                'item_paid' => $item->paid_amount,
                'voucher_discounts' => $voucherDiscounts,
            ];
        }

        // Map terms with payment status (using amount after discount for comparison)
        $termsWithPayment = $tuitionPlan->terms->map(function ($term) use ($termPayments) {
            $payment = $termPayments[$term->id] ?? null;
            $paidAmount = $payment ? $payment['paid_amount'] : 0;
            $originalAmount = $term->amount;

            // If there's an invoice, use amount after discount; otherwise use original amount
            $amountAfterDiscount = $payment ? $payment['amount_after_discount'] : $originalAmount;
            $discountAmount = $payment ? $payment['discount_amount'] : 0;

            // Compare paid amount with amount after discount
            $isPaid = $paidAmount >= $amountAfterDiscount && $amountAfterDiscount > 0;
            $isPartiallyPaid = $paidAmount > 0 && $paidAmount < $amountAfterDiscount;
            $isOverdue = !$isPaid && $term->due_date && $term->due_date->isPast();

            // Remaining amount is based on amount after discount
            $remainingAmount = max(0, $amountAfterDiscount - $paidAmount);

            return [
                'id' => $term->id,
                'term_number' => $term->term_number,
                'amount' => $originalAmount,
                'discount_amount' => $discountAmount,
                'amount_after_discount' => $amountAfterDiscount,
                'due_date' => $term->due_date?->format('Y-m-d'),
                'formatted_due_date' => $term->due_date?->format('d/m/Y'),
                'semester' => $term->semester,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_status' => $isPaid ? 'paid' : ($isPartiallyPaid ? 'partial' : ($isOverdue ? 'overdue' : 'unpaid')),
                'invoices' => $payment['invoices'] ?? [],
            ];
        });

        return Inertia::render('students/AcademicSummary/TuitionPlan', [
            'student' => $student->only(['id', 'student_id', 'full_name', 'status', 'email']),
            'scholarshipAward' => $scholarshipAwardData,
            'tuitionPlan' => [
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
                'terms' => $termsWithPayment,
                'summary' => [
                    'total_amount' => $tuitionPlan->total_amount,
                    'total_discount' => $termsWithPayment->sum('discount_amount'),
                    'total_after_discount' => $termsWithPayment->sum('amount_after_discount'),
                    'total_paid' => $termsWithPayment->sum('paid_amount'),
                    'total_remaining' => $termsWithPayment->sum('remaining_amount'),
                    'paid_count' => $termsWithPayment->filter(fn($t) => $t['payment_status'] === 'paid')->count(),
                    'partial_count' => $termsWithPayment->filter(fn($t) => $t['payment_status'] === 'partial')->count(),
                    'unpaid_count' => $termsWithPayment->filter(fn($t) => $t['payment_status'] === 'unpaid')->count(),
                    'overdue_count' => $termsWithPayment->filter(fn($t) => $t['payment_status'] === 'overdue')->count(),
                ],
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
