<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\SettlementPosition\DngLineHoldingIndex;
use App\Modules\Finance\Support\SettlementPosition\MoneyItemStatusContext;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistPresenter;
use App\Modules\Finance\Support\StudentFinanceLearnerVocabulary;
use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Collection;

/**
 * The single student-facing representation of a current Finance position.
 *
 * Amounts are only exposed when the applicable Settlement Position is valid.
 * This is deliberately a presentation boundary: student controllers must not
 * rebuild balances from cached invoice fields or Eloquent accessors.
 */
final class GetStudentFinancePresentationQuery
{
    public function __construct(
        private readonly StudentFinanceSettlementPositionReader $positionReader,
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly SettlementPositionWorklistPresenter $positionPresenter,
        private readonly DngLineHoldingIndex $dngLineHolding,
    ) {}

    /** @return array<string, mixed> */
    public function handle(int $studentId, ?int $semesterId = null): array
    {
        $position = $this->positionReader->current($studentId, $semesterId);
        $balance = $this->balance($position);
        $valid = (bool) $position['valid'];

        $chargeRows = $valid ? $this->chargeRows($this->chargesFor($studentId, $semesterId)) : collect();
        $pendingCharges = $chargeRows
            ->filter(static fn (array $charge): bool => ! $charge['is_credit']
                && $charge['settlement_position']['valid']
                && $charge['balance'] !== null
                && $charge['balance'] > 0)
            ->values();
        $invoiceRows = $valid ? $this->invoiceRows($studentId, $semesterId) : collect();
        $unpaidInvoices = $invoiceRows->filter(static fn (array $invoice): bool => in_array($invoice['status'], ['open', 'overdue'], true));

        $billingAccountId = BillingAccount::query()
            ->where('student_id', $studentId)
            ->value('id');

        $dngPending = $billingAccountId === null
            ? collect()
            : DngPaymentRequest::query()
                ->where('student_id', $studentId)
                ->where('billing_account_id', $billingAccountId)
                ->whereIn('status', ['pending', 'pushed_to_dng'])
                ->get(['amount']);

        $semester = $semesterId === null ? null : $this->academicPeriods->find($semesterId);

        return [
            'balance' => $balance,
            'pending_payments' => [
                'count' => $pendingCharges->count(),
                'total_amount' => $valid ? $position['remaining_collectible'] : null,
                'items' => $pendingCharges->take(5)->map(static fn (array $charge): array => [
                    'id' => $charge['id'],
                    'description' => $charge['description'],
                    'balance' => $charge['balance'],
                ])->values(),
            ],
            'recent_payments' => Payment::query()
                ->where('student_id', $studentId)
                ->orderByDesc('paid_at')
                ->limit(5)
                ->get(['id', 'amount', 'method', 'paid_at'])
                ->map(static fn (Payment $payment): array => [
                    'id' => (int) $payment->id,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ]),
            'invoices_summary' => [
                'total' => $invoiceRows->count(),
                'paid' => $invoiceRows->where('status', 'paid')->count(),
                'pending' => $invoiceRows->where('status', 'open')->count(),
                'overdue' => $invoiceRows->where('status', 'overdue')->count(),
                'nearest_due_date' => $unpaidInvoices
                    ->filter(static fn (array $invoice): bool => $invoice['due_date'] !== null)
                    ->sortBy('due_date')
                    ->first()['due_date'] ?? null,
            ],
            'dng_pending' => [
                'count' => $dngPending->count(),
                'total_amount' => (float) $dngPending->sum('amount'),
            ],
            'semester' => $semester === null ? null : ['id' => (int) $semester->id, 'name' => (string) $semester->name],
        ];
    }

    /** @return array<string, mixed> */
    public function balanceFor(int $studentId, ?int $semesterId = null): array
    {
        return $this->balance($this->positionReader->current($studentId, $semesterId));
    }

    /** @return array{charges: Collection<int, array<string, mixed>>, summary: array<string, mixed>} */
    public function charges(int $studentId, ?int $semesterId = null, bool $unpaidOnly = false): array
    {
        $position = $this->positionReader->current($studentId, $semesterId);
        $rows = $this->chargeRows($this->chargesFor($studentId, $semesterId));

        if ($unpaidOnly) {
            $rows = $rows->filter(static fn (array $charge): bool => ! $charge['is_fully_paid'])->values();
        }

        return ['charges' => $rows, 'summary' => $this->chargeSummary($position)];
    }

    /** @return array<string, mixed>|null */
    public function charge(int $studentId, int $chargeId): ?array
    {
        $charge = FinanceCharge::query()
            ->where('id', $chargeId)
            ->where('student_id', $studentId)
            ->with(['installments', 'invoiceLines'])
            ->first();

        if ($charge === null) {
            return null;
        }

        return $this->chargeRows(collect([$charge]))->first();
    }

    /** @return array{invoices: Collection<int, array<string, mixed>>, summary: array<string, mixed>} */
    public function invoices(int $studentId, ?int $semesterId = null, ?string $status = null): array
    {
        $position = $this->positionReader->current($studentId, $semesterId);
        $rows = $this->invoiceRows($studentId, $semesterId);

        if ($status !== null) {
            $rows = $rows->where('status', $status)->values();
        }

        return [
            'invoices' => $rows,
            'summary' => $this->invoiceSummary($position),
        ];
    }

    public function invoice(int $studentId, int $invoiceId): ?array
    {
        $invoice = StudentInvoice::query()
            ->where('id', $invoiceId)
            ->where('student_id', $studentId)
            ->with([
                'invoiceLines.charge',
                'invoiceLines.paymentApplications.payment',
                'discounts',
            ])
            ->first();

        if ($invoice === null) {
            return null;
        }

        $semester = $this->academicPeriods->find((int) $invoice->semester_id);
        $position = $this->settlementPositionReader->forInvoice((int) $invoice->id);
        $holding = $this->dngLineHolding->forLineIds(
            $invoice->invoiceLines->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );
        $invoiceContext = $this->invoiceMoneyItemContext($invoice, $position, $holding);
        $meta = $this->settlementMetadata($position, $invoiceContext);
        $hideAmounts = $meta['money_item_status']['hide_amounts'] ?? false;
        $linePositions = collect($position->payable_line_breakdown)
            ->keyBy(static fn (SettlementPosition $linePosition): int => (int) $linePosition->payable_line_id);

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => (string) $invoice->invoice_number,
            'semester' => $semester === null ? null : [
                'id' => $semester->id,
                'name' => $semester->name,
            ],
            ...($hideAmounts ? [
                'subtotal' => null,
                'discount_total' => null,
                'total_amount' => null,
                'paid_amount' => null,
                'credit_amount' => null,
                'remaining' => null,
            ] : $this->invoiceAmounts($position)),
            'status' => $this->invoiceStatus($invoice, $position),
            'due_date' => $invoice->due_date?->toDateString(),
            'paid_at' => $hideAmounts ? null : ($position->isValid() ? $invoice->paid_at?->toIso8601String() : null),
            'lines' => $invoice->invoiceLines->map(function ($line) use ($linePositions, $holding, $invoice): array {
                $linePosition = $linePositions->get((int) $line->id);
                $lineMeta = $this->settlementMetadata(
                    $linePosition,
                    $linePosition instanceof SettlementPosition
                        ? $this->lineMoneyItemContext($line, $linePosition, $holding, $invoice->due_date)
                        : null,
                );
                $lineValid = ! ($lineMeta['money_item_status']['hide_amounts'] ?? false)
                    && $linePosition?->isValid()
                    && $linePosition->amounts !== null;
                $amounts = $linePosition?->amounts;

                return [
                    'id' => (int) $line->id,
                    'description' => (string) $line->description_snapshot,
                    'amount' => $lineValid ? (float) $amounts->gross->amount : null,
                    'charge_type' => $line->charge?->charge_type,
                    'learner_label' => StudentFinanceLearnerVocabulary::chargeLabel(
                        (string) ($line->charge?->charge_type ?? ''),
                        (string) $line->description_snapshot,
                    ),
                    'discount_amount' => $lineValid ? (float) $amounts->discount->amount : null,
                    'paid_amount' => $lineValid ? (float) $amounts->cash->amount : null,
                    'credit_amount' => $lineValid ? (float) $amounts->credit->amount : null,
                    'remaining_amount' => $lineValid ? (float) $amounts->remaining->amount : null,
                    'is_fully_paid' => $lineValid && $amounts->remaining->isZero(),
                    'payments' => $lineValid
                        ? $line->paymentApplications->map(static fn ($application): array => [
                            'payment_id' => (int) $application->payment_id,
                            'amount' => (float) $application->amount,
                            'method' => $application->payment?->method,
                            'paid_at' => $application->payment?->paid_at?->toIso8601String(),
                        ])
                        : [],
                    'settlement_position' => $lineMeta,
                ];
            }),
            'discounts' => $hideAmounts
                ? []
                : $invoice->discounts->map(static fn ($discount): array => [
                    'id' => (int) $discount->id,
                    'description' => (string) $discount->description,
                    'amount' => (float) $discount->amount,
                ]),
            'settlement_position' => $meta,
        ];
    }

    /** @return Collection<int, FinanceCharge> */
    private function chargesFor(int $studentId, ?int $semesterId): Collection
    {
        return FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
            ->with(['installments', 'invoiceLines'])
            ->orderBy('effective_at')
            ->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function invoiceRows(int $studentId, ?int $semesterId): Collection
    {
        $invoices = StudentInvoice::query()
            ->where('student_id', $studentId)
            ->when($semesterId !== null, fn ($query) => $query->where('semester_id', $semesterId))
            ->with(['invoiceLines.charge.financeObligation'])
            ->withCount('invoiceLines')
            ->orderByDesc('created_at')
            ->get();
        $positions = $invoices->isEmpty()
            ? []
            : $this->settlementPositionReader->batch(
                $invoices->map(static fn (StudentInvoice $invoice): SettlementPositionScope => SettlementPositionScope::invoice((int) $invoice->id))->all(),
            );

        $periods = $this->academicPeriods->findMany(
            $invoices->pluck('semester_id')
                ->filter()
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
        );

        $holding = $this->dngLineHolding->forLineIds(
            $invoices->flatMap(static fn (StudentInvoice $invoice) => $invoice->invoiceLines->pluck('id'))
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );

        return $invoices->values()->map(fn (StudentInvoice $invoice, int $index): array => $this->invoiceRow(
            $invoice,
            $positions[$index] ?? null,
            $periods[(int) $invoice->semester_id] ?? null,
            $holding,
        ));
    }

    /** @param array<string, mixed> $position */
    private function balance(array $position): array
    {
        $valid = (bool) $position['valid'];

        return [
            'total_charges' => $valid ? $position['gross'] : null,
            'total_credits' => $valid ? $position['discount'] + $position['credit_applied'] : null,
            'discount_amount' => $valid ? $position['discount'] : null,
            'net_charges' => $valid ? $position['net_due'] : null,
            'total_paid' => $valid ? $position['cash_applied'] : null,
            'applied_credit' => $valid ? $position['credit_applied'] : null,
            'balance' => $valid ? $position['remaining_collectible'] : null,
            'unapplied_credit' => $valid ? $position['unapplied_cash'] : null,
            'status' => $valid ? $position['status'] : SettlementPosition::STATE_INVALID,
            'learner_terms' => StudentFinanceLearnerVocabulary::balanceTerms(),
            'settlement_position' => [
                'valid' => $valid,
                'mode' => $position['position_mode'],
                'state' => $position['settlement_state'],
                'message' => $valid ? null : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
                'issues' => $position['issues'],
            ],
        ];
    }

    /** @param array<string, mixed> $position */
    private function chargeSummary(array $position): array
    {
        $valid = (bool) $position['valid'];

        return [
            'total_charges' => $valid ? $position['gross'] : null,
            'total_credits' => $valid ? $position['discount'] + $position['credit_applied'] : null,
            'discount_amount' => $valid ? $position['discount'] : null,
            'net_amount' => $valid ? $position['net_due'] : null,
            'cash_amount' => $valid ? $position['cash_applied'] : null,
            'credit_amount' => $valid ? $position['credit_applied'] : null,
            'remaining_amount' => $valid ? $position['remaining_collectible'] : null,
            'settlement_position' => $this->summaryMetadata($position),
        ];
    }

    /** @param array<string, mixed> $position */
    private function invoiceSummary(array $position): array
    {
        $valid = (bool) $position['valid'];

        return [
            'total_invoiced' => $valid ? $position['net_due'] : null,
            'total_paid' => $valid ? $position['cash_applied'] : null,
            'total_credit_applied' => $valid ? $position['credit_applied'] : null,
            'total_outstanding' => $valid ? $position['remaining_collectible'] : null,
            'settlement_position' => $this->summaryMetadata($position),
        ];
    }

    private function summaryMetadata(array $position): array
    {
        $valid = (bool) $position['valid'];
        $remaining = $valid ? (float) $position['remaining_collectible'] : null;
        $code = ! $valid
            ? MoneyItemStatusContext::REVIEWING
            : ($remaining !== null && $remaining <= 0 ? MoneyItemStatusContext::COMPLETED : MoneyItemStatusContext::AWAITING_PAYMENT);

        return [
            'valid' => $valid,
            'mode' => $position['position_mode'],
            'state' => $position['settlement_state'],
            'message' => $valid ? null : StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
            'issues' => $position['issues'],
            'money_item_status' => [
                'code' => $code,
                'label_staff' => match ($code) {
                    MoneyItemStatusContext::REVIEWING => 'Đang rà soát',
                    MoneyItemStatusContext::COMPLETED => 'Đã hoàn tất',
                    default => 'Chờ thanh toán',
                },
                'label_student' => match ($code) {
                    MoneyItemStatusContext::REVIEWING => StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
                    MoneyItemStatusContext::COMPLETED => 'Khoản này đã hoàn tất.',
                    default => 'Khoản này đang chờ thanh toán.',
                },
                'hide_amounts' => $code === MoneyItemStatusContext::REVIEWING,
            ],
        ];
    }

    /** @param Collection<int, FinanceCharge> $charges */
    private function chargeRows(Collection $charges): Collection
    {
        $lineIds = $charges->flatMap(static fn (FinanceCharge $charge) => $charge->invoiceLines->pluck('id'))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $positions = $lineIds->isEmpty()
            ? []
            : $this->settlementPositionReader->batch(
                $lineIds->map(static fn (int $id): SettlementPositionScope => SettlementPositionScope::payableLine($id))->all(),
            );
        $positionsByLine = collect($positions)->keyBy(static fn (SettlementPosition $position): int => (int) $position->payable_line_id);
        $holding = $this->dngLineHolding->forLineIds($lineIds->all());

        return $charges->map(function (FinanceCharge $charge) use ($positionsByLine, $holding): array {
            $linePositions = $charge->invoiceLines
                ->map(fn ($line) => $positionsByLine->get((int) $line->id));
            $firstLine = $charge->invoiceLines->first();
            $firstPosition = $firstLine ? $positionsByLine->get((int) $firstLine->id) : null;
            $meta = $firstPosition instanceof SettlementPosition && $firstLine
                ? $this->settlementMetadata($firstPosition, $this->lineMoneyItemContext($firstLine, $firstPosition, $holding, null))
                : $this->invalidChargeMetadata($linePositions);
            $hideAmounts = $meta['money_item_status']['hide_amounts'] ?? false;
            $valid = ! $hideAmounts
                && $linePositions->isNotEmpty()
                && $linePositions->every(static fn (?SettlementPosition $position): bool => $position?->isValid() && $position->amounts !== null);

            return [
                'id' => (int) $charge->id,
                'description' => (string) $charge->description,
                'charge_type' => (string) $charge->charge_type,
                'learner_label' => StudentFinanceLearnerVocabulary::chargeLabel(
                    (string) $charge->charge_type,
                    (string) $charge->description,
                ),
                'amount' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->gross->amount) : null,
                'is_charge' => (bool) $charge->is_charge,
                'is_credit' => (bool) $charge->is_credit,
                'paid_amount' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->cash->amount) : null,
                'discount_amount' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->discount->amount) : null,
                'credit_amount' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->credit->amount) : null,
                'balance' => $valid ? (float) $linePositions->sum(static fn (SettlementPosition $position): float => (float) $position->amounts->remaining->amount) : null,
                'is_fully_paid' => $valid && $linePositions->every(static fn (SettlementPosition $position): bool => $position->amounts->remaining->isZero()),
                'installments' => $charge->installments,
                ...$this->chargeInstallmentContext($charge),
                'settlement_position' => $meta,
            ];
        })->values();
    }

    private function invalidChargeMetadata(Collection $positions): array
    {
        $invalidPosition = $positions->first(static fn (?SettlementPosition $position): bool => ! $position?->isValid());

        return $this->settlementMetadata($invalidPosition);
    }

    /**
     * @param  array<int, array{holding: bool, needs_review: bool}>  $holding
     * @return array<string, mixed>
     */
    private function invoiceRow(
        StudentInvoice $invoice,
        ?SettlementPosition $position,
        ?AcademicPeriodReference $semester,
        array $holding = [],
    ): array {
        $context = $position instanceof SettlementPosition
            ? $this->invoiceMoneyItemContext($invoice, $position, $holding)
            : null;
        $meta = $this->settlementMetadata($position, $context);
        $amounts = ($meta['money_item_status']['hide_amounts'] ?? false)
            ? [
                'subtotal' => null,
                'discount_total' => null,
                'total_amount' => null,
                'paid_amount' => null,
                'credit_amount' => null,
                'remaining' => null,
            ]
            : $this->invoiceAmounts($position);

        return [
            'id' => (int) $invoice->id,
            'invoice_number' => (string) $invoice->invoice_number,
            'semester' => $semester === null ? null : [
                'id' => $semester->id,
                'name' => $semester->name,
            ],
            ...$amounts,
            'status' => $this->invoiceStatus($invoice, $position),
            'due_date' => $invoice->due_date?->toDateString(),
            'paid_at' => ($meta['money_item_status']['hide_amounts'] ?? false) ? null : ($position?->isValid() ? $invoice->paid_at?->toIso8601String() : null),
            'line_count' => (int) $invoice->invoice_lines_count,
            'settlement_position' => $meta,
        ];
    }

    /** @return array{subtotal: float|null, discount_total: float|null, total_amount: float|null, paid_amount: float|null, credit_amount: float|null, remaining: float|null} */
    private function invoiceAmounts(?SettlementPosition $position): array
    {
        $valid = $position?->isValid() && $position->amounts !== null;
        $amounts = $position?->amounts;

        return [
            'subtotal' => $valid ? (float) $amounts->gross->amount : null,
            'discount_total' => $valid ? (float) $amounts->discount->amount : null,
            'total_amount' => $valid ? (float) $amounts->netDue()->amount : null,
            'paid_amount' => $valid ? (float) $amounts->cash->amount : null,
            'credit_amount' => $valid ? (float) $amounts->credit->amount : null,
            'remaining' => $valid ? (float) $amounts->remaining->amount : null,
        ];
    }

    private function invoiceStatus(StudentInvoice $invoice, ?SettlementPosition $position): string
    {
        if (! $position?->isValid() || $position->amounts === null) {
            return SettlementPosition::STATE_INVALID;
        }

        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            return (string) $invoice->status;
        }

        if ($position->amounts->gross->isZero()) {
            return 'zero_amount';
        }

        if ($position->amounts->remaining->isZero()) {
            return 'paid';
        }

        return $invoice->due_date?->isPast() ? 'overdue' : 'open';
    }

    /** @return array{valid: bool, mode: string, state: string, message: string|null, issues: list<array<string, mixed>>} */
    private function settlementMetadata(?SettlementPosition $position, ?MoneyItemStatusContext $context = null): array
    {
        if ($position === null) {
            $moneyItemStatus = [
                'code' => MoneyItemStatusContext::REVIEWING,
                'label_staff' => 'Đang rà soát',
                'label_student' => StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
                'hide_amounts' => true,
            ];

            return [
                'valid' => false,
                'mode' => SettlementPosition::MODE_CURRENT,
                'state' => SettlementPosition::STATE_MISSING,
                'message' => $moneyItemStatus['label_student'],
                'issues' => [[
                    'code' => 'settlement_position.missing',
                    'blocking' => true,
                    'evidence' => [],
                ]],
                'money_item_status' => $moneyItemStatus,
            ];
        }

        $moneyItemStatus = $this->positionPresenter->moneyItemStatus(
            $context ?? new MoneyItemStatusContext($position),
        );

        return [
            'valid' => $position->isValid(),
            'mode' => $position->position_mode,
            'state' => $position->settlement_state,
            'message' => $moneyItemStatus['hide_amounts'] ? $moneyItemStatus['label_student'] : null,
            'issues' => array_map(static fn ($issue): array => [
                'code' => $issue->code,
                'blocking' => $issue->blocking,
                'evidence' => $issue->evidence,
            ], $position->issues),
            'money_item_status' => $moneyItemStatus,
        ];
    }

    /**
     * @param  array<int, array{holding: bool, needs_review: bool}>  $holding
     */
    private function invoiceMoneyItemContext(StudentInvoice $invoice, SettlementPosition $position, array $holding): MoneyItemStatusContext
    {
        $holdingAny = false;
        $reviewAny = false;
        foreach ($invoice->invoiceLines as $line) {
            $flags = $holding[(int) $line->id] ?? ['holding' => false, 'needs_review' => false];
            $holdingAny = $holdingAny || $flags['holding'];
            $reviewAny = $reviewAny || $flags['needs_review'];
        }

        return new MoneyItemStatusContext(
            position: $position,
            dngHoldingThisItem: $holdingAny,
            dngNeedsReview: $reviewAny,
            obligationCancelled: $invoice->status === 'cancelled',
            chargeVoid: $invoice->invoiceLines->isNotEmpty()
                && $invoice->invoiceLines->every(static fn ($line): bool => ($line->status ?? 'active') !== 'active'
                    || $line->charge?->status === FinanceCharge::STATUS_VOID),
            dueDate: $invoice->due_date,
        );
    }

    /**
     * @param  array<int, array{holding: bool, needs_review: bool}>  $holding
     */
    private function lineMoneyItemContext(mixed $line, SettlementPosition $position, array $holding, mixed $dueDate): MoneyItemStatusContext
    {
        $flags = $holding[(int) $line->id] ?? ['holding' => false, 'needs_review' => false];

        return new MoneyItemStatusContext(
            position: $position,
            dngHoldingThisItem: $flags['holding'],
            dngNeedsReview: $flags['needs_review'],
            chargeVoid: ($line->status ?? 'active') !== 'active' || $line->charge?->status === FinanceCharge::STATUS_VOID,
            dueDate: $dueDate,
        );
    }

    /**
     * @return array{installment_no: ?int, installments_total: ?int, due_date: ?string}
     */
    private function chargeInstallmentContext(FinanceCharge $charge): array
    {
        $installments = $charge->installments ?? collect();
        $total = $installments->count();
        $current = $installments->firstWhere('status', FinanceChargeInstallment::STATUS_AWAITING_PAYMENT)
            ?? $installments->firstWhere('status', FinanceChargeInstallment::STATUS_PENDING);

        return [
            'installment_no' => $current === null ? null : (int) $current->installment_no,
            'installments_total' => $total > 0 ? $total : null,
            'due_date' => $current?->due_date?->toDateString(),
        ];
    }
}
