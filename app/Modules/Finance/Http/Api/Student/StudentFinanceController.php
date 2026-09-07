<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateStudentDngPaymentAccessAction;
use App\Modules\Finance\Dng\Exceptions\StudentDngPaymentAccessUnavailable;
use App\Modules\Finance\Dng\Exceptions\StudentDngPaymentRequestNotFound;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Http\Requests\StudentFinance\ListStudentFinanceChargesRequest;
use App\Modules\Finance\Http\Requests\StudentFinance\ListStudentFinanceInvoicesRequest;
use App\Modules\Finance\Http\Requests\StudentFinance\StudentFinanceBalanceRequest;
use App\Modules\Finance\Http\Requests\StudentFinance\StudentFinanceOverviewRequest;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\GetStudentFinancePresentationQuery;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StudentFinanceController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private SettlementPositionReader $settlementPositionReader,
        private GetStudentFinancePresentationQuery $studentFinance,
        private DngCampusCodeResolver $dngCampusCodeResolver,
        private StudentReferenceReader $studentReferences,
    ) {}

    /**
     * Get student balance summary.
     */
    public function balance(StudentFinanceBalanceRequest $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        return ApiResponse::success([
            'balance' => $this->studentFinance->balanceFor((int) $student->id, $request->semesterId()),
            'semester_id' => $request->semesterId(),
        ]);
    }

    /**
     * Get student charges.
     */
    public function charges(ListStudentFinanceChargesRequest $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        return ApiResponse::success($this->studentFinance->charges(
            (int) $student->id,
            $request->semesterId(),
            $request->boolean('unpaid'),
        ));
    }

    /**
     * Get student payment history with allocation details.
     */
    public function payments(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'method' => 'nullable|string|in:cash,bank_transfer,gateway,wallet,import,other',
        ]);

        $query = Payment::where('student_id', $student->id)
            ->with([
                'applications.invoiceLine.charge',
                'applications.invoiceLine.invoice',
            ])
            ->orderBy('paid_at', 'desc');

        if (! empty($validated['from'])) {
            $query->whereDate('paid_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('paid_at', '<=', $validated['to']);
        }

        if (! empty($validated['method'])) {
            $query->where('method', $validated['method']);
        }

        $payments = $query->get();

        $payments->each(function ($payment) {
            $payment->append(['allocated_amount', 'unapplied_amount', 'is_fully_allocated']);
            $payment->setRelation('allocations', $this->formatAllocations($payment));
        });

        $totalPaid = $payments->sum('amount');
        $totalAllocated = $payments->sum('allocated_amount');

        return ApiResponse::success([
            'payments' => $payments->map(fn ($p) => $this->formatPayment($p)),
            'unapplied_credit' => $this->paymentService->getUnappliedCredits($student->id),
            'summary' => [
                'total_paid' => (float) $totalPaid,
                'total_allocated' => (float) $totalAllocated,
                'total_unapplied' => (float) max(0, $totalPaid - $totalAllocated),
            ],
        ]);
    }

    /**
     * Get specific payment detail with full allocation tree.
     */
    public function paymentDetail(Request $request, int $paymentId): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $payment = Payment::where('id', $paymentId)
            ->where('student_id', $student->id)
            ->with([
                'applications.invoiceLine.charge.semester',
                'applications.invoiceLine.invoice',
            ])
            ->first();

        if (! $payment) {
            return ApiResponse::error('Payment not found', [], 404);
        }

        $payment->append(['allocated_amount', 'unapplied_amount', 'is_fully_allocated']);

        // Load DNG request if payment came from DNG gateway
        $dngRequest = null;
        if ($payment->source === 'dng' && $payment->external_ref) {
            $dngPaymentId = str_replace('DNG-', '', (string) $payment->external_ref);
            $dngRequest = DngPaymentRequest::where('student_id', $student->id)
                ->where('dng_payment_id', $dngPaymentId)
                ->first();
        }

        $allocations = $payment->applications->map(function ($app) {
            $line = $app->invoiceLine;
            $charge = $line?->charge;
            $invoice = $line?->invoice;

            return [
                'id' => $app->id,
                'charge_description' => $line?->description_snapshot ?? $charge?->description,
                'charge_type' => $charge?->charge_type,
                'semester' => $charge?->semester?->name,
                'invoice_number' => $invoice?->invoice_number,
                'amount' => (float) $app->amount,
                'entry_type' => $app->entry_type,
                'applied_at' => $app->applied_at?->toIso8601String(),
            ];
        });

        return ApiResponse::success([
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'source' => $payment->source,
            'external_ref' => $payment->external_ref,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'status' => $payment->status,
            'notes' => $payment->notes,
            'allocated_amount' => $payment->allocated_amount,
            'unapplied_amount' => $payment->unapplied_amount,
            'allocations' => $allocations,
            'dng_request' => $dngRequest ? [
                'id' => $dngRequest->id,
                'dng_payment_id' => $dngRequest->dng_payment_id,
                'status' => $dngRequest->status,
                'invoice_serial_number' => $dngRequest->invoice_serial_number,
            ] : null,
        ]);
    }

    /**
     * Get specific charge details.
     */
    public function chargeDetail(Request $request, int $chargeId): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $charge = $this->studentFinance->charge((int) $student->id, $chargeId);

        if (! $charge) {
            return ApiResponse::error('Charge not found', [], 404);
        }

        return ApiResponse::success([
            'charge' => $charge,
        ]);
    }

    /**
     * List student's DNG payment requests (individual records).
     * Use for displaying each request separately with per-request QR/installment actions.
     */
    public function dngRequests(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,pushed_to_dng,paid_uninvoiced,paid_invoiced,reconciled,failed,cancelled,unknown_outcome,needs_review',
        ]);

        $billingAccountId = BillingAccount::query()
            ->where('student_id', $student->id)
            ->value('id');

        if ($billingAccountId === null) {
            return ApiResponse::success(['dng_requests' => [], 'summary' => [
                'total_pending' => 0,
                'total_paid' => 0,
                'count_pending' => 0,
                'count_paid' => 0,
            ]]);
        }

        $query = DngPaymentRequest::where('student_id', $student->id)
            ->where('billing_account_id', $billingAccountId)
            ->orderBy('created_at', 'desc');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $requests = $query->get();

        $pendingStatuses = [DngPaymentRequest::STATUS_PENDING, DngPaymentRequest::STATUS_PUSHED_TO_DNG];
        $paidStatuses = [DngPaymentRequest::STATUS_PAID_UNINVOICED, DngPaymentRequest::STATUS_PAID_INVOICED, DngPaymentRequest::STATUS_RECONCILED];

        $pendingItems = $requests->filter(fn ($r) => in_array($r->status, $pendingStatuses, true));
        $paidItems = $requests->filter(fn ($r) => in_array($r->status, $paidStatuses, true));
        $contexts = $this->dngInstallmentContexts($requests);

        return ApiResponse::success([
            'dng_requests' => $requests->map(function ($r) use ($student, $contexts) {
                return [
                    'id' => $r->id,
                    'dng_payment_id' => $r->dng_payment_id,
                    'amount' => (float) $r->amount,
                    'fee_type' => $r->fee_type,
                    'description' => $r->description,
                    'item_id' => $r->item_id,
                    'status' => $r->status,
                    'paid_at' => $r->paid_at?->toIso8601String(),
                    'invoice_serial_number' => $r->invoice_serial_number,
                    'invoice_date' => $r->invoice_date?->toDateString(),
                    'created_at' => $r->created_at?->toIso8601String(),
                    'payment_access' => [
                        'status' => $this->hasSafeStudentDngAccessMetadata($r, (int) $student->id)
                            && $r->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG
                            ? 'available'
                            : 'unavailable',
                    ],
                    ...($contexts[(int) $r->id] ?? [
                        'installment_no' => null,
                        'installments_total' => null,
                        'due_date' => $r->due_date?->toDateString(),
                    ]),
                ];
            }),
            'summary' => [
                'total_pending' => (float) $pendingItems->sum('amount'),
                'total_paid' => (float) $paidItems->sum('amount'),
                'count_pending' => $pendingItems->count(),
                'count_paid' => $paidItems->count(),
            ],
        ]);
    }

    /**
     * Get consolidated pending DNG summary for paying all outstanding fees at once.
     * Use POST /dng/qr or /dng/installment to generate a single payment link covering all fee_types.
     */
    public function dngRequestsAll(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $pendingStatuses = [DngPaymentRequest::STATUS_PENDING, DngPaymentRequest::STATUS_PUSHED_TO_DNG];
        $paidStatuses = [DngPaymentRequest::STATUS_PAID_UNINVOICED, DngPaymentRequest::STATUS_PAID_INVOICED, DngPaymentRequest::STATUS_RECONCILED];
        $activeCollectionStatuses = [
            DngPaymentRequest::STATUS_PENDING,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG,
            DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
            DngPaymentRequest::STATUS_NEEDS_REVIEW,
        ];
        $billingAccountId = BillingAccount::query()
            ->where('student_id', $student->id)
            ->value('id');

        if ($billingAccountId === null) {
            return ApiResponse::success([
                'pending' => [
                    'total_amount' => 0,
                    'count' => 0,
                    'breakdown' => [],
                    'payment_access' => ['status' => 'unavailable'],
                ],
                'paid_requests' => [],
                'summary' => [
                    'total_pending' => 0,
                    'total_paid' => 0,
                    'count_pending' => 0,
                    'count_paid' => 0,
                ],
            ]);
        }

        $allRequests = DngPaymentRequest::where('student_id', $student->id)
            ->where('billing_account_id', $billingAccountId)
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingItems = $allRequests->filter(fn ($r) => in_array($r->status, $pendingStatuses, true));
        $activeCollectionItems = $allRequests->filter(fn ($r) => in_array($r->status, $activeCollectionStatuses, true));
        $paidItems = $allRequests->filter(fn ($r) => in_array($r->status, $paidStatuses, true));

        $pendingBreakdown = $pendingItems
            ->groupBy('fee_type')
            ->map(fn ($group) => $group->sortByDesc('created_at')->first())
            ->map(fn ($r) => [
                'fee_type' => $r->fee_type,
                'amount' => (float) $r->amount,
                'description' => $r->description,
                'status' => $r->status,
            ])
            ->values();

        return ApiResponse::success([
            'pending' => [
                'total_amount' => (float) $pendingItems->sum('amount'),
                'count' => $pendingItems->count(),
                'breakdown' => $pendingBreakdown,
                'payment_access' => [
                    'status' => $activeCollectionItems->count() === 1
                        && $activeCollectionItems->first()->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG
                        && $this->hasSafeStudentDngAccessMetadata($activeCollectionItems->first(), (int) $student->id)
                        ? 'available'
                        : 'unavailable',
                ],
            ],
            'paid_requests' => $paidItems->map(fn ($r) => [
                'id' => $r->id,
                'dng_payment_id' => $r->dng_payment_id,
                'amount' => (float) $r->amount,
                'fee_type' => $r->fee_type,
                'description' => $r->description,
                'status' => $r->status,
                'paid_at' => $r->paid_at?->toIso8601String(),
                'invoice_serial_number' => $r->invoice_serial_number,
                'invoice_date' => $r->invoice_date?->toDateString(),
            ])->values(),
            'summary' => [
                'total_pending' => (float) $pendingItems->sum('amount'),
                'total_paid' => (float) $paidItems->sum('amount'),
                'count_pending' => $pendingItems->count(),
                'count_paid' => $paidItems->count(),
            ],
        ]);
    }

    /**
     * Get single DNG payment request detail.
     */
    public function dngRequestDetail(Request $request, int $dngRequestId): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $billingAccountId = BillingAccount::query()
            ->where('student_id', $student->id)
            ->value('id');

        if ($billingAccountId === null) {
            return ApiResponse::notFound('DNG request not found');
        }

        $dngRequest = DngPaymentRequest::where('id', $dngRequestId)
            ->where('student_id', $student->id)
            ->where('billing_account_id', $billingAccountId)
            ->with('payment')
            ->first();

        if (! $dngRequest) {
            return ApiResponse::error('DNG request not found', [], 404);
        }

        return ApiResponse::success([
            'id' => $dngRequest->id,
            'dng_payment_id' => $dngRequest->dng_payment_id,
            'amount' => (float) $dngRequest->amount,
            'fee_type' => $dngRequest->fee_type,
            'description' => $dngRequest->description,
            'item_id' => $dngRequest->item_id,
            'status' => $dngRequest->status,
            'payment' => $dngRequest->payment ? [
                'id' => $dngRequest->payment->id,
                'amount' => (float) $dngRequest->payment->amount,
                'paid_at' => $dngRequest->payment->paid_at?->toIso8601String(),
                'status' => $dngRequest->payment->status,
            ] : null,
            'invoice_serial_number' => $dngRequest->invoice_serial_number,
            'invoice_date' => $dngRequest->invoice_date?->toDateString(),
            'created_at' => $dngRequest->created_at?->toIso8601String(),
            'updated_at' => $dngRequest->updated_at?->toIso8601String(),
            ...$this->dngInstallmentContext($dngRequest),
        ]);
    }

    /**
     * Create QR/virtual-account access for all pushed_to_dng requests of the authenticated student.
     * No request ID needed — BE consolidates all pending fee_types automatically.
     */
    public function dngQr(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        return $this->createStudentDngPaymentAccess($student, 'qr');
    }

    /**
     * Create installment/Foxpay access for all pushed_to_dng requests of the authenticated student.
     * No request ID needed — BE consolidates all pending fee_types automatically.
     */
    public function dngInstallment(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        return $this->createStudentDngPaymentAccess($student, 'installment');
    }

    /**
     * Create QR/virtual-account access data for the student's DNG request.
     * All awaitingPayment fee_types for this student are included so DNG consolidates them into one payment.
     */
    public function dngRequestQr(Request $request, int $dngRequestId): JsonResponse
    {
        return $this->createStudentDngPaymentAccess($request->user(), 'qr', $dngRequestId);
    }

    /**
     * Create installment/Foxpay access data for the student's DNG request.
     * All awaitingPayment fee_types for this student are included so DNG consolidates them into one payment.
     */
    public function dngRequestInstallment(Request $request, int $dngRequestId): JsonResponse
    {
        return $this->createStudentDngPaymentAccess($request->user(), 'installment', $dngRequestId);
    }

    /**
     * List student's invoices.
     */
    public function invoices(ListStudentFinanceInvoicesRequest $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        return ApiResponse::success($this->studentFinance->invoices(
            (int) $student->id,
            $request->semesterId(),
            $request->validated('status'),
        ));
    }

    private function createStudentDngPaymentAccess(
        ?Student $student,
        string $paymentMethod,
        ?int $dngRequestId = null,
    ): JsonResponse {
        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        try {
            return ApiResponse::success(CreateStudentDngPaymentAccessAction::run([
                'student_id' => (int) $student->id,
                'payment_method' => $paymentMethod,
                'dng_request_id' => $dngRequestId,
            ]));
        } catch (StudentDngPaymentRequestNotFound) {
            return ApiResponse::notFound('DNG request not found');
        } catch (StudentDngPaymentAccessUnavailable) {
            return ApiResponse::error(
                StudentDngPaymentAccessUnavailable::MESSAGE,
                [[
                    'code' => StudentDngPaymentAccessUnavailable::CODE,
                    'field' => null,
                    'detail' => null,
                ]],
                422,
            );
        }
    }

    private function hasSafeStudentDngAccessMetadata(DngPaymentRequest $request, int $studentId): bool
    {
        $student = $this->studentReferences->find($studentId);
        if ($student === null) {
            return false;
        }

        try {
            $campusCode = $this->dngCampusCodeResolver->requireForCampusId($student->campusId);
        } catch (ValidationException) {
            return false;
        }

        $metadataSafe = $request->student_id === $student->id
            && $request->billing_account_id !== null
            && $request->provider_rail === 'dng'
            && $request->campus_code === $campusCode
            && $request->student_code === $student->studentCode
            && filled($request->item_id)
            && (float) $request->amount > 0;

        if (! $metadataSafe) {
            return false;
        }

        $targetLineIds = $request->reservationTargets()
            ->pluck('invoice_line_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($targetLineIds === []) {
            return true;
        }

        $position = $this->settlementPositionReader->forPayableLines($targetLineIds);

        return $position->isValid()
            && $position->amounts !== null
            && $position->amounts->remaining->minor_amount === Money::vnd((string) $request->amount)->minor_amount;
    }

    /**
     * Get invoice detail with lines and payment applications.
     */
    public function invoiceDetail(Request $request, int $invoiceId): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $invoice = $this->studentFinance->invoice((int) $student->id, $invoiceId);

        if (! $invoice) {
            return ApiResponse::error('Invoice not found', [], 404);
        }

        return ApiResponse::success($invoice);
    }

    /**
     * Get financial overview dashboard for student.
     */
    public function overview(StudentFinanceOverviewRequest $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        return ApiResponse::success($this->studentFinance->handle((int) $student->id, $request->semesterId()));
    }

    /**
     * Format allocations for a payment.
     */
    private function formatAllocations(Payment $payment): Collection
    {
        return $payment->applications->map(function ($app) {
            $line = $app->invoiceLine;
            $charge = $line?->charge;
            $invoice = $line?->invoice;

            return [
                'charge_description' => $line?->description_snapshot ?? $charge?->description,
                'charge_type' => $charge?->charge_type,
                'invoice_number' => $invoice?->invoice_number,
                'amount' => (float) $app->amount,
                'applied_at' => $app->applied_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Format a single payment for list response.
     */
    private function formatPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'method' => $payment->method,
            'source' => $payment->source,
            'external_ref' => $payment->external_ref,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'status' => $payment->status,
            'allocated_amount' => $payment->allocated_amount,
            'unapplied_amount' => $payment->unapplied_amount,
            'is_fully_allocated' => $payment->is_fully_allocated,
            'allocations' => $payment->getRelation('allocations') ?? collect(),
        ];
    }

    /**
     * @return array{installment_no: ?int, installments_total: ?int, due_date: ?string}
     */
    private function dngInstallmentContext(DngPaymentRequest $request): array
    {
        return $this->dngInstallmentContexts(collect([$request]))[(int) $request->id];
    }

    /**
     * @param  Collection<int, DngPaymentRequest>  $requests
     * @return array<int, array{installment_no: ?int, installments_total: ?int, due_date: ?string}>
     */
    private function dngInstallmentContexts(Collection $requests): array
    {
        if ($requests->isEmpty()) {
            return [];
        }

        $installments = FinanceChargeInstallment::query()
            ->whereIn('dng_payment_request_id', $requests->pluck('id')->all())
            ->get()
            ->keyBy(fn (FinanceChargeInstallment $row): int => (int) $row->dng_payment_request_id);

        $chargeIds = $installments
            ->pluck('finance_charge_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $totals = $chargeIds === []
            ? []
            : FinanceChargeInstallment::query()
                ->whereIn('finance_charge_id', $chargeIds)
                ->selectRaw('finance_charge_id, COUNT(*) as aggregate_total')
                ->groupBy('finance_charge_id')
                ->pluck('aggregate_total', 'finance_charge_id')
                ->all();

        $out = [];
        foreach ($requests as $request) {
            $installment = $installments->get((int) $request->id);
            $out[(int) $request->id] = [
                'installment_no' => $installment === null ? null : (int) $installment->installment_no,
                'installments_total' => $installment === null
                    ? null
                    : (int) ($totals[$installment->finance_charge_id] ?? 0),
                'due_date' => $installment?->due_date?->toDateString()
                    ?? $request->due_date?->toDateString(),
            ];
        }

        return $out;
    }
}
