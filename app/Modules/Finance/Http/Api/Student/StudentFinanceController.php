<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Payment;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentFinanceController extends Controller
{
    public function __construct(
        private FinanceChargeService $chargeService,
        private PaymentService $paymentService
    ) {}

    /**
     * Get student balance summary.
     */
    public function balance(Request $request): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;
        $balance = $this->paymentService->getStudentBalance($student->id, $semesterId);

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
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;
        $charges = $this->chargeService->getStudentCharges($student->id, $semesterId);

        // Add computed attributes
        $charges->each(function ($charge) {
            $charge->append(['is_charge', 'is_credit', 'paid_amount', 'discount_amount', 'balance', 'is_fully_paid']);
        });

        // Filter unpaid charges only — accepts ?unpaid=true|1
        if ($request->boolean('unpaid')) {
            $charges = $charges->filter(fn ($c) => ! $c->is_fully_paid)->values();
        }

        return ApiResponse::success([
            'charges' => $charges,
            'summary' => [
                'total_charges' => $this->chargeService->getTotalCharges($student->id, $semesterId),
                'total_credits' => $this->chargeService->getTotalCredits($student->id, $semesterId),
                'net_amount' => $this->chargeService->getNetAmount($student->id, $semesterId),
            ],
        ]);
    }

    /**
     * Get student payment history with allocation details.
     */
    public function payments(Request $request): JsonResponse
    {
        $student = $request->user('student');

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
        $student = $request->user('student');

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
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $charge = \App\Models\FinanceCharge::where('id', $chargeId)
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
     * Get student's DNG payment summary.
     * Pending requests are consolidated into one object (total + breakdown by fee_type).
     * Paid history is returned as a flat list for display.
     */
    public function dngRequests(Request $request): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $pendingStatuses = [
            DngPaymentRequest::STATUS_PENDING,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        ];

        $paidStatuses = [
            DngPaymentRequest::STATUS_PAID_UNINVOICED,
            DngPaymentRequest::STATUS_PAID_INVOICED,
            DngPaymentRequest::STATUS_RECONCILED,
        ];

        $allRequests = DngPaymentRequest::where('student_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingItems = $allRequests->filter(fn ($r) => in_array($r->status, $pendingStatuses, true));
        $paidItems = $allRequests->filter(fn ($r) => in_array($r->status, $paidStatuses, true));

        // Consolidate pending: group by fee_type, keep latest per type
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
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $dngRequest = DngPaymentRequest::where('id', $dngRequestId)
            ->where('student_id', $student->id)
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
    public function dngQr(Request $request, DngPaymentService $dngPaymentService): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $anchor = DngPaymentRequest::where('student_id', $student->id)
            ->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)
            ->latest('created_at')
            ->first();

        if (! $anchor) {
            return ApiResponse::error('Không có khoản phí nào đang chờ thanh toán.', [], 422);
        }

        $feeTypes = DngPaymentRequest::where('student_id', $student->id)
            ->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)
            ->pluck('fee_type')
            ->unique()
            ->values()
            ->all();

        $providerResponse = $dngPaymentService->createQrAccess($anchor, $feeTypes);

        return ApiResponse::success($this->formatConsolidatedPaymentAccessResponse('qr', $providerResponse));
    }

    /**
     * Create installment/Foxpay access for all pushed_to_dng requests of the authenticated student.
     * No request ID needed — BE consolidates all pending fee_types automatically.
     */
    public function dngInstallment(Request $request, DngPaymentService $dngPaymentService): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $anchor = DngPaymentRequest::where('student_id', $student->id)
            ->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)
            ->latest('created_at')
            ->first();

        if (! $anchor) {
            return ApiResponse::error('Không có khoản phí nào đang chờ thanh toán.', [], 422);
        }

        $feeTypes = DngPaymentRequest::where('student_id', $student->id)
            ->where('status', DngPaymentRequest::STATUS_PUSHED_TO_DNG)
            ->pluck('fee_type')
            ->unique()
            ->values()
            ->all();

        $providerResponse = $dngPaymentService->createInstallmentAccess($anchor, $feeTypes);

        return ApiResponse::success($this->formatConsolidatedPaymentAccessResponse('installment', $providerResponse));
    }

    /**
     * Create QR/virtual-account access data for the student's DNG request.
     * All awaitingPayment fee_types for this student are included so DNG consolidates them into one payment.
     */
    public function dngRequestQr(Request $request, int $dngRequestId, DngPaymentService $dngPaymentService): JsonResponse
    {
        $dngRequest = $this->resolveOwnedDngRequest($request, $dngRequestId);

        if (! $dngRequest) {
            return ApiResponse::notFound('DNG request not found');
        }

        $feeTypes = $this->resolveAllPendingFeeTypes($dngRequest);
        $providerResponse = $dngPaymentService->createQrAccess($dngRequest, $feeTypes);

        return ApiResponse::success($this->formatPaymentAccessResponse('qr', $dngRequest, $providerResponse));
    }

    /**
     * Create installment/Foxpay access data for the student's DNG request.
     * All awaitingPayment fee_types for this student are included so DNG consolidates them into one payment.
     */
    public function dngRequestInstallment(Request $request, int $dngRequestId, DngPaymentService $dngPaymentService): JsonResponse
    {
        $dngRequest = $this->resolveOwnedDngRequest($request, $dngRequestId);

        if (! $dngRequest) {
            return ApiResponse::notFound('DNG request not found');
        }

        $feeTypes = $this->resolveAllPendingFeeTypes($dngRequest);

        $providerResponse = $dngPaymentService->createInstallmentAccess($dngRequest, $feeTypes);

        return ApiResponse::success($this->formatPaymentAccessResponse('installment', $dngRequest, $providerResponse));
    }

    /**
     * List student's invoices.
     */
    public function invoices(Request $request): JsonResponse
    {
        $student = $request->user('student');

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

        if (! empty($validated['status'])) {
            $query->filterByStatus($validated['status']);
        }

        $invoices = $query->get();

        $totalInvoiced = $invoices->sum('total_amount');
        $totalPaid = $invoices->sum('paid_amount');

        return ApiResponse::success([
            'invoices' => $invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'semester' => $inv->semester ? ['id' => $inv->semester->id, 'name' => $inv->semester->name] : null,
                'subtotal' => (float) $inv->subtotal,
                'discount_total' => (float) $inv->discount_total,
                'total_amount' => (float) $inv->total_amount,
                'paid_amount' => (float) $inv->paid_amount,
                'remaining' => (float) $inv->outstanding_balance,
                'status' => $inv->real_time_status,
                'due_date' => $inv->due_date?->toDateString(),
                'paid_at' => $inv->paid_at?->toIso8601String(),
                'line_count' => $inv->invoice_lines_count,
            ]),
            'summary' => [
                'total_invoiced' => (float) $totalInvoiced,
                'total_paid' => (float) $totalPaid,
                'total_outstanding' => (float) max(0, $totalInvoiced - $totalPaid),
            ],
        ]);
    }

    /**
     * Collect all unique fee_types from awaitingPayment DNG requests for the same student.
     * Always includes the triggering request's fee_type, even if its status has just changed.
     *
     * @return array<int, string>
     */
    private function resolveAllPendingFeeTypes(DngPaymentRequest $dngRequest): array
    {
        $feeTypes = DngPaymentRequest::where('student_id', $dngRequest->student_id)
            ->awaitingPayment()
            ->pluck('fee_type')
            ->push($dngRequest->fee_type)
            ->unique()
            ->values()
            ->all();

        return $feeTypes;
    }

    private function resolveOwnedDngRequest(Request $request, int $dngRequestId): ?DngPaymentRequest
    {
        $student = $request->user('student');

        if (! $student) {
            return null;
        }

        return DngPaymentRequest::query()
            ->where('id', $dngRequestId)
            ->where('student_id', $student->id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $providerResponse
     * @return array<string, mixed>
     */
    private function formatConsolidatedPaymentAccessResponse(string $paymentMethod, array $providerResponse): array
    {
        $providerData = is_array($providerResponse['data'] ?? null) ? $providerResponse['data'] : [];
        $paymentUrl = $providerData['PaymentUrl'] ?? $providerData['payment_url'] ?? $providerData['LinkQRCode'] ?? null;

        return [
            'payment_method' => $paymentMethod,
            'payment_url' => $paymentUrl,
            'provider_response' => $providerResponse,
        ];
    }

    /**
     * @param  array<string, mixed>  $providerResponse
     * @return array<string, mixed>
     */
    private function formatPaymentAccessResponse(string $paymentMethod, DngPaymentRequest $dngRequest, array $providerResponse): array
    {
        $providerData = is_array($providerResponse['data'] ?? null) ? $providerResponse['data'] : [];
        $paymentUrl = $providerData['PaymentUrl'] ?? $providerData['payment_url'] ?? $providerData['LinkQRCode'] ?? null;

        return [
            'dng_request_id' => $dngRequest->id,
            'payment_method' => $paymentMethod,
            'status' => $dngRequest->fresh()->status,
            'payment_url' => $paymentUrl,
            'provider_response' => $providerResponse,
        ];
    }

    /**
     * Get invoice detail with lines and payment applications.
     */
    public function invoiceDetail(Request $request, int $invoiceId): JsonResponse
    {
        $student = $request->user('student');

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

        $lines = $invoice->invoiceLines->map(function ($line) {
            $linePaid = $line->paymentApplications->sum('amount');

            return [
                'id' => $line->id,
                'description' => $line->description_snapshot,
                'amount' => (float) $line->amount_snapshot,
                'charge_type' => $line->charge?->charge_type,
                'paid_amount' => (float) $linePaid,
                'is_fully_paid' => $linePaid >= $line->amount_snapshot,
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
            'subtotal' => (float) $invoice->subtotal,
            'discount_total' => (float) $invoice->discount_total,
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'status' => $invoice->real_time_status,
            'due_date' => $invoice->due_date?->toDateString(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'lines' => $lines,
            'discounts' => $discounts,
        ]);
    }

    /**
     * Get financial overview dashboard for student.
     */
    public function overview(Request $request): JsonResponse
    {
        $student = $request->user('student');

        if (! $student) {
            return ApiResponse::error('Unauthorized', [], 401);
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = isset($validated['semester_id']) ? (int) $validated['semester_id'] : null;

        // Balance summary
        $balance = $this->paymentService->getStudentBalance($student->id, $semesterId);

        // Pending charges
        $outstandingCharges = $this->paymentService->getOutstandingCharges($student->id, $semesterId);

        // Recent payments (latest 5)
        $recentPayments = Payment::where('student_id', $student->id)
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get(['id', 'amount', 'method', 'paid_at']);

        // Invoice summary counts
        $invoices = StudentInvoice::where('student_id', $student->id)
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->with(['invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
            ->get();

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

        $dngPending = DngPaymentRequest::where('student_id', $student->id)
            ->whereIn('status', $dngPendingStatuses)
            ->get(['amount']);

        // Resolve semester for response
        $semester = null;
        if ($semesterId) {
            $semester = \App\Models\Semester::find($semesterId, ['id', 'name']);
        }

        return ApiResponse::success([
            'balance' => $balance,
            'pending_payments' => [
                'count' => $outstandingCharges->count(),
                'total_amount' => (float) $outstandingCharges->sum('balance'),
                'items' => $outstandingCharges->take(5)->map(fn ($c) => [
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

    /**
     * Format allocations for a payment.
     */
    private function formatAllocations(Payment $payment): \Illuminate\Support\Collection
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
