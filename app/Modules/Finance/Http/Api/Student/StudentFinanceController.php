<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateStudentDngPaymentAccessAction;
use App\Modules\Finance\Dng\Exceptions\StudentDngPaymentAccessUnavailable;
use App\Modules\Finance\Dng\Exceptions\StudentDngPaymentRequestNotFound;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StudentFinanceController extends Controller
{
    public function __construct(
        private FinanceChargeService $chargeService,
        private PaymentService $paymentService,
        private StudentFinanceSettlementPositionReader $positionReader,
        private SettlementPositionReader $settlementPositionReader,
    ) {}

    /**
     * Get student balance summary.
     */
    public function balance(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;
        $balance = $this->studentBalance($this->positionReader->current($student->id, $semesterId));

        return ApiResponse::success([
            'balance' => $balance,
            'semester_id' => $semesterId,
        ]);
    }

    /**
     * Get student charges.
     */
    public function charges(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;
        $charges = $this->chargeService->getStudentCharges($student->id, $semesterId);

        // Eager-load installments so the student app can render the per-installment
        // table without an extra round-trip.
        $charges->loadMissing('installments');

        $charges->loadMissing('invoiceLines');
        $chargeRows = $this->chargeRows($charges);

        // Filter unpaid charges only — accepts ?unpaid=true|1
        if ($request->boolean('unpaid')) {
            $chargeRows = $chargeRows->filter(fn (array $charge): bool => ! $charge['is_fully_paid'])->values();
        }

        return ApiResponse::success([
            'charges' => $chargeRows,
            'summary' => $this->chargeService->getChargeSummary($student->id, $semesterId),
        ]);
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

        $charge = FinanceCharge::where('id', $chargeId)
            ->where('student_id', $student->id)
            ->with(['semester', 'invoiceLines.paymentApplications.payment'])
            ->first();

        if (! $charge) {
            return ApiResponse::error('Charge not found', [], 404);
        }

        $charge->append(['is_charge', 'is_credit', 'paid_amount', 'balance', 'is_fully_paid']);

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

        return ApiResponse::success([
            'dng_requests' => $requests->map(fn ($r) => [
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
                    'status' => $this->hasSafeStudentDngAccessMetadata($r, $student)
                        && $r->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG
                        ? 'available'
                        : 'unavailable',
                ],
            ]),
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
                        && $this->hasSafeStudentDngAccessMetadata($activeCollectionItems->first(), $student)
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
    public function invoices(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'status' => 'nullable|string|in:draft,pending,paid,overdue,cancelled,open,zero_amount,issued',
        ]);

        $query = StudentInvoice::where('student_id', $student->id)
            ->with('semester')
            ->withCount('invoiceLines')
            ->orderBy('created_at', 'desc');

        if (! empty($validated['semester_id'])) {
            $query->where('semester_id', $validated['semester_id']);
        }

        $invoices = $query->get();
        $positions = $this->invoicePositions($invoices);
        $rows = $invoices->values()->map(function (StudentInvoice $invoice, int $index) use ($positions): array {
            return $this->invoiceListRow($invoice, $positions[$index] ?? null);
        });

        if (! empty($validated['status'])) {
            $rows = $rows->filter(fn (array $row): bool => $row['status'] === $validated['status'])->values();
        }

        $summaryPosition = $this->positionReader->current(
            (int) $student->id,
            isset($validated['semester_id']) ? (int) $validated['semester_id'] : null,
        );
        $summaryValid = (bool) $summaryPosition['valid'];

        return ApiResponse::success([
            'invoices' => $rows,
            'summary' => [
                'total_invoiced' => $summaryValid ? $summaryPosition['net_due'] : null,
                'total_paid' => $summaryValid ? $summaryPosition['cash_applied'] : null,
                'total_credit_applied' => $summaryValid ? $summaryPosition['credit_applied'] : null,
                'total_outstanding' => $summaryValid ? $summaryPosition['remaining_collectible'] : null,
                'settlement_position' => [
                    'valid' => $summaryValid,
                    'message' => $summaryValid ? null : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
                ],
            ],
        ]);
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
                'student' => $student,
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

    private function hasSafeStudentDngAccessMetadata(DngPaymentRequest $request, Student $student): bool
    {
        $student->loadMissing('campus');

        $metadataSafe = $request->student_id === $student->id
            && $request->billing_account_id !== null
            && $request->provider_rail === 'dng'
            && $request->campus_code === $student->campus?->getDngCode()
            && $request->student_code === $student->student_id
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

        $invoice = StudentInvoice::where('id', $invoiceId)
            ->where('student_id', $student->id)
            ->with([
                'semester',
                'invoiceLines.charge',
                'invoiceLines.paymentApplications.payment',
                'discounts',
            ])
            ->first();

        if (! $invoice) {
            return ApiResponse::error('Invoice not found', [], 404);
        }

        $position = $this->settlementPositionReader->forInvoice((int) $invoice->id);
        $valid = $position->isValid() && $position->amounts !== null;
        $linePositions = collect($position->payable_line_breakdown)
            ->keyBy(fn (SettlementPosition $linePosition): int => (int) $linePosition->payable_line_id);

        $lines = $invoice->invoiceLines->map(function ($line) use ($linePositions, $valid) {
            $linePosition = $linePositions->get((int) $line->id);
            $lineValid = $valid && $linePosition?->isValid() && $linePosition->amounts !== null;
            $amounts = $linePosition?->amounts;

            return [
                'id' => $line->id,
                'description' => $line->description_snapshot,
                'amount' => $lineValid ? (float) $amounts->gross->amount : null,
                'charge_type' => $line->charge?->charge_type,
                'discount_amount' => $lineValid ? (float) $amounts->discount->amount : null,
                'paid_amount' => $lineValid ? (float) $amounts->cash->amount : null,
                'credit_amount' => $lineValid ? (float) $amounts->credit->amount : null,
                'remaining_amount' => $lineValid ? (float) $amounts->remaining->amount : null,
                'is_fully_paid' => $lineValid && $amounts->remaining->isZero(),
                'payments' => $line->paymentApplications->map(fn ($app) => [
                    'payment_id' => $app->payment_id,
                    'amount' => (float) $app->amount,
                    'method' => $app->payment?->method,
                    'paid_at' => $app->payment?->paid_at?->toIso8601String(),
                ]),
            ];
        });

        $discounts = $invoice->discounts->map(fn ($d) => [
            'id' => $d->id,
            'description' => $d->description,
            'amount' => (float) $d->amount,
        ]);

        return ApiResponse::success([
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'semester' => $invoice->semester ? ['id' => $invoice->semester->id, 'name' => $invoice->semester->name] : null,
            'subtotal' => $valid ? (float) $position->amounts->gross->amount : null,
            'discount_total' => $valid ? (float) $position->amounts->discount->amount : null,
            'total_amount' => $valid ? (float) $position->amounts->netDue()->amount : null,
            'paid_amount' => $valid ? (float) $position->amounts->cash->amount : null,
            'credit_amount' => $valid ? (float) $position->amounts->credit->amount : null,
            'remaining' => $valid ? (float) $position->amounts->remaining->amount : null,
            'status' => $this->invoiceStatus($invoice, $position),
            'due_date' => $invoice->due_date?->toDateString(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'lines' => $lines,
            'discounts' => $discounts,
            'settlement_position' => $this->settlementMetadata($position),
        ]);
    }

    /**
     * Get financial overview dashboard for student.
     */
    public function overview(Request $request): JsonResponse
    {
        $student = $request->user();

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;

        // Balance summary comes from the Finance-owned current Settlement Position.
        $position = $this->positionReader->current($student->id, $semesterId);
        $balance = $this->studentBalance($position);

        // Pending charges
        $outstandingCharges = $this->paymentService->getOutstandingCharges($student->id, $semesterId);
        $pendingCharges = $position['valid'] ? $outstandingCharges : collect();

        // Recent payments (latest 5)
        $recentPayments = Payment::where('student_id', $student->id)
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get(['id', 'amount', 'method', 'paid_at']);

        // Invoice summary counts
        $invoices = $position['valid']
            ? StudentInvoice::where('student_id', $student->id)
                ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
                ->with(['invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
                ->get()
            : collect();

        $unpaidInvoices = $invoices->filter(fn ($inv) => in_array($inv->real_time_status, ['open', 'overdue'], true));

        $invoiceCounts = [
            'total' => $invoices->count(),
            'paid' => $invoices->filter(fn ($inv) => $inv->real_time_status === 'paid')->count(),
            'pending' => $unpaidInvoices->filter(fn ($inv) => $inv->real_time_status === 'open')->count(),
            'overdue' => $unpaidInvoices->filter(fn ($inv) => $inv->real_time_status === 'overdue')->count(),
            'nearest_due_date' => $unpaidInvoices
                ->filter(fn ($inv) => $inv->due_date !== null)
                ->sortBy('due_date')
                ->first()?->due_date?->toDateString(),
        ];

        // DNG pending
        $dngPendingStatuses = [
            DngPaymentRequest::STATUS_PENDING,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        ];

        $billingAccountId = BillingAccount::query()
            ->where('student_id', $student->id)
            ->value('id');

        $dngPending = DngPaymentRequest::where('student_id', $student->id)
            ->where('billing_account_id', $billingAccountId)
            ->whereIn('status', $dngPendingStatuses)
            ->get(['amount']);

        // Resolve semester for response
        $semester = null;
        if ($semesterId) {
            $semester = Semester::find($semesterId, ['id', 'name']);
        }

        return ApiResponse::success([
            'balance' => $balance,
            'pending_payments' => [
                'count' => $pendingCharges->count(),
                'total_amount' => $position['remaining_collectible'] ?? null,
                'items' => $pendingCharges->take(5)->map(fn ($c) => [
                    'id' => $c->id,
                    'description' => $c->description,
                    'balance' => (float) $c->balance,
                ])->values(),
            ],
            'recent_payments' => $recentPayments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ]),
            'invoices_summary' => $invoiceCounts,
            'dng_pending' => [
                'count' => $dngPending->count(),
                'total_amount' => (float) $dngPending->sum('amount'),
            ],
            'semester' => $semester ? ['id' => $semester->id, 'name' => $semester->name] : null,
        ]);
    }

    /** @param array<string, mixed> $position */
    private function studentBalance(array $position): array
    {
        $valid = (bool) ($position['valid'] ?? false);

        return [
            'total_charges' => $valid ? $position['gross'] : null,
            'total_credits' => $valid ? $position['discount'] : null,
            'net_charges' => $valid ? $position['net_due'] : null,
            'total_paid' => $valid ? $position['cash_applied'] : null,
            'balance' => $valid ? $position['remaining_collectible'] : null,
            'unapplied_credit' => $valid ? $position['unapplied_cash'] : null,
            'applied_credit' => $valid ? $position['credit_applied'] : null,
            'status' => $position['status'] ?? 'unknown',
            'settlement_position' => [
                'valid' => $valid,
                'mode' => $position['position_mode'] ?? 'current',
                'state' => $position['settlement_state'] ?? 'invalid',
                'message' => $valid ? null : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
            ],
        ];
    }

    /**
     * @param  Collection<int, StudentInvoice>  $invoices
     * @return list<SettlementPosition>
     */
    private function invoicePositions(Collection $invoices): array
    {
        if ($invoices->isEmpty()) {
            return [];
        }

        return $this->settlementPositionReader->batch(
            $invoices->map(
                static fn (StudentInvoice $invoice): SettlementPositionScope => SettlementPositionScope::invoice((int) $invoice->id),
            )->all(),
        );
    }

    /** @return array<string, mixed> */
    private function invoiceListRow(StudentInvoice $invoice, ?SettlementPosition $position): array
    {
        $valid = $position?->isValid() && $position->amounts !== null;
        $amounts = $position?->amounts;

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => (string) $invoice->invoice_number,
            'semester' => $invoice->semester === null ? null : [
                'id' => (int) $invoice->semester->id,
                'name' => (string) $invoice->semester->name,
            ],
            'subtotal' => $valid ? (float) $amounts->gross->amount : null,
            'discount_total' => $valid ? (float) $amounts->discount->amount : null,
            'total_amount' => $valid ? (float) $amounts->netDue()->amount : null,
            'paid_amount' => $valid ? (float) $amounts->cash->amount : null,
            'credit_amount' => $valid ? (float) $amounts->credit->amount : null,
            'remaining' => $valid ? (float) $amounts->remaining->amount : null,
            'status' => $this->invoiceStatus($invoice, $position),
            'due_date' => $invoice->due_date?->toDateString(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'line_count' => (int) $invoice->invoice_lines_count,
            'settlement_position' => $position === null ? [
                'valid' => false,
                'mode' => SettlementPosition::MODE_CURRENT,
                'state' => SettlementPosition::STATE_MISSING,
                'message' => StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
            ] : $this->settlementMetadata($position),
        ];
    }

    private function invoiceStatus(StudentInvoice $invoice, ?SettlementPosition $position): string
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            return (string) $invoice->status;
        }

        if (! $position?->isValid() || $position->amounts === null) {
            return SettlementPosition::STATE_INVALID;
        }

        if ($position->amounts->gross->isZero()) {
            return 'zero_amount';
        }

        if ($position->amounts->remaining->isZero()) {
            return 'paid';
        }

        return $invoice->due_date?->isPast() ? 'overdue' : 'open';
    }

    /** @param Collection<int, FinanceCharge> $charges */
    private function chargeRows(Collection $charges): Collection
    {
        $lineIds = $charges->flatMap(fn (FinanceCharge $charge) => $charge->invoiceLines->pluck('id'))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $positions = $lineIds->isEmpty()
            ? []
            : $this->settlementPositionReader->batch(
                $lineIds->map(static fn (int $id): SettlementPositionScope => SettlementPositionScope::payableLine($id))->all(),
            );
        $positionsByLine = collect($positions)->keyBy(static fn (SettlementPosition $position): int => (int) $position->payable_line_id);

        return $charges->map(function (FinanceCharge $charge) use ($positionsByLine): array {
            $linePositions = $charge->invoiceLines
                ->map(fn ($line) => $positionsByLine->get((int) $line->id))
                ->filter();
            $valid = $linePositions->isNotEmpty()
                && $linePositions->every(static fn (SettlementPosition $position): bool => $position->isValid() && $position->amounts !== null);

            return [
                'id' => (int) $charge->id,
                'description' => (string) $charge->description,
                'charge_type' => (string) $charge->charge_type,
                'amount' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->gross->amount) : null,
                'is_charge' => (bool) $charge->is_charge,
                'is_credit' => (bool) $charge->is_credit,
                'paid_amount' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->cash->amount) : null,
                'discount_amount' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->discount->amount) : null,
                'credit_amount' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->credit->amount) : null,
                'balance' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->remaining->amount) : null,
                'is_fully_paid' => $valid && $linePositions->every(static fn (SettlementPosition $position): bool => $position->amounts->remaining->isZero()),
                'installments' => $charge->installments,
                'settlement_position' => [
                    'valid' => $valid,
                    'mode' => SettlementPosition::MODE_CURRENT,
                    'state' => $valid
                        ? $linePositions->first()->settlement_state
                        : SettlementPosition::STATE_INVALID,
                    'message' => $valid ? null : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
                ],
            ];
        })->values();
    }

    /** @return array{valid:bool,mode:string,state:string,message:?string} */
    private function settlementMetadata(SettlementPosition $position): array
    {
        return [
            'valid' => $position->isValid(),
            'mode' => $position->position_mode,
            'state' => $position->settlement_state,
            'message' => $position->isValid()
                ? null
                : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
        ];
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
}
