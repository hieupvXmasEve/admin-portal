<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Modules\Finance\Support\SettlementPosition\DngLineHoldingIndex;
use App\Modules\Finance\Support\SettlementPosition\MoneyItemStatusContext;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistPresenter;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ListSettlementWorklistQuery
{
    private const SORTABLE = [
        'student_code' => 'student_code',
        'student_name' => 'student_name',
        'invoice_count' => 'invoice_count',
        'active_due' => 'active_due',
        'unapplied_balance' => 'unapplied_balance',
        'net_amount_to_collect' => 'net_amount_to_collect',
    ];

    public function __construct(
        private readonly SettlementPositionWorklistReader $positionReader,
        private readonly SettlementPositionWorklistPresenter $positionPresenter,
        private readonly StudentReferenceReader $studentReferences,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly DngLineHoldingIndex $dngLineHolding,
    ) {}

    /** @return array<string,mixed> */
    public function handle(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'readiness' => 'nullable|string|in:all,ready,no_cash',
            'dng_status' => 'nullable|string|in:all,has_dng,no_dng',
            'student_status' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:1|max:500',
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|string|max:50',
            'direction' => 'nullable|in:asc,desc',
        ]);

        $readiness = $validated['readiness'] ?? 'all';
        $dngStatus = $validated['dng_status'] ?? 'all';
        $studentStatus = $validated['student_status'] ?? '';
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 50);
        $page = (int) ($validated['page'] ?? 1);
        $sort = self::SORTABLE[$validated['sort'] ?? 'active_due'] ?? self::SORTABLE['active_due'];
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        $campusStudentIds = $campusId === null ? null : $this->studentReferences->idsForCampus((int) $campusId);
        $matchingStudentIds = $search === '' ? [] : $this->studentReferences->idsMatchingSearch($search, $campusId === null ? null : (int) $campusId);

        $invoiceQuery = StudentInvoice::query()
            ->with([
                'semester',
                'invoiceLines' => fn ($query) => $query->where('status', 'active')->with('charge.financeObligation'),
            ])
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('due_date');

        if ($campusStudentIds !== null) {
            $invoiceQuery->whereIn('student_id', $campusStudentIds);
        }

        if ($search !== '') {
            $invoiceQuery->where(function ($query) use ($search, $matchingStudentIds): void {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereIn('student_id', $matchingStudentIds);
            });
        }

        $invoices = $invoiceQuery->get();
        $studentIds = $invoices->pluck('student_id')->map(static fn (int|string $id): int => (int) $id)->unique()->values()->all();
        $studentReferences = $this->studentReferences->findMany($studentIds);
        $enrollments = $this->programEnrollments->forStudentIds($studentIds);

        if ($studentStatus !== '') {
            $invoices = $invoices->filter(static fn (StudentInvoice $invoice): bool => ($enrollments[(int) $invoice->student_id] ?? null)?->legacyCompatibleStatus() === $studentStatus)->values();
        }
        $lineIdsByStudent = [];
        $lineIdsByInvoice = [];
        $lineIdsByStudentFeeType = [];

        foreach ($invoices as $invoice) {
            $invoiceLines = $invoice->invoiceLines;
            $lineIdsByInvoice[(int) $invoice->id] = $invoiceLines->pluck('id')->map(fn ($id): int => (int) $id)->all();

            foreach ($invoiceLines as $line) {
                $studentId = (int) $invoice->student_id;
                $lineId = (int) $line->id;
                $feeType = DngFeeTypeOptions::fromChargeType((string) ($line->charge?->charge_type ?? ''));

                $lineIdsByStudent[$studentId][] = $lineId;
                $lineIdsByStudentFeeType[$studentId.'|'.$feeType][] = $lineId;
            }
        }

        $positionsByStudent = $this->positionReader->forLineGroups($lineIdsByStudent);
        $positionsByInvoice = $this->positionReader->forLineGroups($lineIdsByInvoice);
        $positionsByStudentFeeType = $this->positionReader->forLineGroups($lineIdsByStudentFeeType);
        $dngHolding = $this->dngLineHolding->forLineIds(array_merge(...array_values($lineIdsByInvoice) ?: [[]]));
        $studentIds = collect(array_keys($lineIdsByStudent))->map(fn ($id): int => (int) $id)->values();

        $paymentsByStudent = Payment::query()
            ->with('applications')
            ->whereIn('student_id', $studentIds)
            ->where('status', Payment::STATUS_COMPLETED)
            ->orderBy('paid_at')
            ->get()
            ->groupBy('student_id');

        $latestDngRequestsByStudent = DngPaymentRequest::query()
            ->whereIn('student_id', $studentIds)
            ->latest('created_at')
            ->get(['id', 'student_id', 'status', 'item_id', 'description', 'created_at'])
            ->groupBy('student_id')
            ->map(fn (Collection $requests) => $requests->first());

        $activeDngByStudentAndFeeType = DngPaymentRequest::query()
            ->whereIn('student_id', $studentIds)
            ->awaitingPayment()
            ->latest('created_at')
            ->get(['id', 'student_id', 'fee_type', 'amount', 'status'])
            ->groupBy('student_id')
            ->map(fn (Collection $requests) => $requests->groupBy('fee_type')
                ->map(fn (Collection $byType) => $byType->first()));

        $students = $invoices
            ->groupBy('student_id')
            ->map(function (Collection $studentInvoices, int|string $studentId) use (
                $positionsByStudent,
                $positionsByInvoice,
                $positionsByStudentFeeType,
                $paymentsByStudent,
                $latestDngRequestsByStudent,
                $activeDngByStudentAndFeeType,
                $studentReferences,
                $enrollments,
                $dngHolding,
            ): array {
                $studentId = (int) $studentId;
                $student = $studentReferences[$studentId] ?? null;
                $enrollment = $enrollments[$studentId] ?? null;
                $position = $positionsByStudent[$studentId] ?? null;
                $payments = $paymentsByStudent->get($studentId, collect());
                $unappliedBalance = $this->positionPresenter->unappliedCash($payments);
                $positionSummary = $position instanceof SettlementPosition
                    ? $this->positionPresenter->summarize($position, $unappliedBalance > 0)
                    : $this->missingSummary();
                $activeDue = $positionSummary['remaining'];
                $actionable = $positionSummary['valid'] && $activeDue !== null && $activeDue > 0 && $unappliedBalance > 0;
                $latestDngRequest = $latestDngRequestsByStudent->get($studentId);

                return [
                    'student_id' => $studentId,
                    'student_code' => $student?->studentCode,
                    'student_name' => $student?->fullName,
                    'student_status' => $enrollment?->legacyCompatibleStatus(),
                    'invoice_count' => $studentInvoices->count(),
                    'overdue_invoice_count' => $studentInvoices->filter(fn (StudentInvoice $invoice): bool => $invoice->due_date?->isPast() ?? false)->count(),
                    'active_due' => $activeDue,
                    'unapplied_balance' => $unappliedBalance,
                    'allocated_amount' => (float) $payments->sum(fn (Payment $payment): float => (float) $payment->applications->sum('amount')),
                    'total_payments' => (float) $payments->sum('amount'),
                    'net_amount_to_collect' => $this->positionPresenter->netAmountToCollect($activeDue, $unappliedBalance),
                    'actionable' => $actionable,
                    'needs_review' => ! $positionSummary['valid'],
                    'settlement_state' => $positionSummary['settlement_state'],
                    'settlement_label' => $positionSummary['settlement_label'],
                    'money_item_status' => $positionSummary['money_item_status'],
                    'settlement_issues' => $positionSummary['issues'],
                    'gross' => $positionSummary['gross'],
                    'discount' => $positionSummary['discount'],
                    'cash' => $positionSummary['cash'],
                    'credit' => $positionSummary['credit'],
                    'latest_dng_request' => $latestDngRequest ? [
                        'id' => $latestDngRequest->id,
                        'status' => $latestDngRequest->status,
                        'item_id' => $latestDngRequest->item_id,
                        'description' => $latestDngRequest->description,
                        'created_at' => $latestDngRequest->created_at?->toIso8601String(),
                    ] : null,
                    'fee_type_breakdown' => $this->feeTypeBreakdown(
                        $studentId,
                        $positionsByStudentFeeType,
                        $activeDngByStudentAndFeeType->get($studentId, collect()),
                    ),
                    'invoices' => $studentInvoices->map(function (StudentInvoice $invoice) use ($positionsByInvoice, $dngHolding): array {
                        $invoicePosition = $positionsByInvoice[(int) $invoice->id] ?? null;
                        $summary = $invoicePosition instanceof SettlementPosition
                            ? $this->positionPresenter->summarize(
                                $invoicePosition,
                                false,
                                $this->moneyItemContext($invoice, $invoicePosition, $dngHolding),
                            )
                            : $this->missingSummary();

                        return [
                            'id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'semester_id' => $invoice->semester_id,
                            'semester_name' => $invoice->semester?->name,
                            'status' => $summary['valid'] && $summary['remaining'] !== null && $summary['remaining'] <= 0 ? 'paid' : ($summary['valid'] ? $invoice->status : 'needs_review'),
                            'settlement_state' => $summary['settlement_state'],
                            'settlement_label' => $summary['settlement_label'],
                            'money_item_status' => $summary['money_item_status'],
                            'settlement_issues' => $summary['issues'],
                            'due_date' => $invoice->due_date?->toDateString(),
                            'total_amount' => $summary['net'],
                            'paid_amount' => $summary['cash'],
                            'remaining_amount' => $summary['remaining'],
                        ];
                    })->values(),
                ];
            })
            ->filter(function (array $student) use ($readiness, $dngStatus): bool {
                $passReadiness = match ($readiness) {
                    'ready' => $student['actionable'],
                    'no_cash' => $student['needs_review'] === false && ! $student['actionable'],
                    default => true,
                };

                $passDng = match ($dngStatus) {
                    'has_dng' => $student['latest_dng_request'] !== null,
                    'no_dng' => $student['latest_dng_request'] === null,
                    default => true,
                };

                return $passReadiness && $passDng;
            })
            ->values();

        $exceptions = $students->filter(fn (array $student): bool => $student['needs_review'])->values();
        $students = $students
            ->filter(fn (array $student): bool => $student['needs_review'] === false && $student['active_due'] !== null && $student['active_due'] > 0)
            ->values();

        $students = $students->sort(function (array $left, array $right) use ($sort, $direction): int {
            $leftRank = [
                $left['actionable'] ? 1 : 0,
                $left['needs_review'] ? 1 : 0,
                $left['overdue_invoice_count'],
                $left[$sort] ?? 0,
                $left['student_code'],
            ];
            $rightRank = [
                $right['actionable'] ? 1 : 0,
                $right['needs_review'] ? 1 : 0,
                $right['overdue_invoice_count'],
                $right[$sort] ?? 0,
                $right['student_code'],
            ];

            return $direction === 'asc' ? ($leftRank <=> $rightRank) : ($rightRank <=> $leftRank);
        })->values();

        $summary = [
            'students_with_unpaid_invoices' => $students->count(),
            'ready_students' => $students->where('actionable', true)->count(),
            'needs_review_students' => $exceptions->count(),
            'total_active_due' => (float) $students->where('needs_review', false)->sum('active_due'),
            'total_unapplied_balance' => (float) $students->sum('unapplied_balance'),
        ];

        return [
            'students' => $this->paginateCollection($students, $perPage, $page, $request->url(), $request->query()),
            'exceptions' => $exceptions->all(),
            'summary' => $summary,
            'allocation_priority_options' => ObligationTypeRegistry::allocationPriorityOptions(),
            'filters' => [
                'search' => $search,
                'readiness' => $readiness,
                'dng_status' => $dngStatus,
                'student_status' => $studentStatus,
                'per_page' => $perPage,
                'page' => $page,
                'sort' => array_search($sort, self::SORTABLE, true) ?: 'active_due',
                'direction' => $direction,
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function missingSummary(): array
    {
        return [
            'valid' => false,
            'settlement_state' => SettlementPosition::STATE_MISSING,
            'settlement_label' => 'Cần kiểm tra',
            'money_item_status' => [
                'code' => MoneyItemStatusContext::REVIEWING,
                'label_staff' => 'Đang rà soát',
                'label_student' => 'Khoản này đang được nhà trường kiểm tra. Vui lòng quay lại sau.',
                'hide_amounts' => true,
            ],
            'gross' => null,
            'discount' => null,
            'cash' => null,
            'credit' => null,
            'net' => null,
            'remaining' => null,
            'issues' => [[
                'code' => 'settlement_position.missing_payable_line',
                'severity' => 'blocking',
                'blocking' => true,
                'evidence' => [],
                'finance_invariant_code' => null,
            ]],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function feeTypeBreakdown(int $studentId, array $positions, Collection $activeDngByFeeType): array
    {
        $labels = collect(DngFeeTypeOptions::all())->pluck('label', 'value')->all();
        $prefix = $studentId.'|';
        $result = [];

        foreach ($positions as $key => $position) {
            if (! str_starts_with((string) $key, $prefix)) {
                continue;
            }

            $feeType = substr((string) $key, strlen($prefix));
            $summary = $this->positionPresenter->summarize($position);

            if (! $summary['valid'] || $summary['remaining'] === null || $summary['remaining'] <= 0) {
                continue;
            }

            $activeDng = $activeDngByFeeType->get($feeType);
            $result[] = [
                'fee_type' => $feeType,
                'label' => $labels[$feeType] ?? $feeType,
                'gross' => $summary['gross'],
                'discount' => $summary['discount'],
                'net_remaining' => $summary['remaining'],
                'semester_id' => null,
                'active_dng' => $activeDng ? [
                    'id' => $activeDng->id,
                    'amount' => (float) $activeDng->amount,
                    'status' => $activeDng->status,
                ] : null,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array{holding: bool, needs_review: bool}>  $dngHolding
     */
    private function moneyItemContext(StudentInvoice $invoice, SettlementPosition $position, array $dngHolding): MoneyItemStatusContext
    {
        $holding = false;
        $needsReview = false;
        foreach ($invoice->invoiceLines as $line) {
            $flags = $dngHolding[(int) $line->id] ?? ['holding' => false, 'needs_review' => false];
            $holding = $holding || $flags['holding'];
            $needsReview = $needsReview || $flags['needs_review'];
        }

        $chargeVoid = $invoice->invoiceLines->isNotEmpty()
            && $invoice->invoiceLines->every(static fn ($line): bool => ($line->status ?? 'active') !== 'active'
                || $line->charge?->status === FinanceCharge::STATUS_VOID);
        $obligationCancelled = $invoice->status === 'cancelled'
            || $invoice->invoiceLines->contains(static fn ($line): bool => in_array(
                (string) ($line->charge?->financeObligation?->lifecycle_status ?? ''),
                [FinanceObligation::STATUS_CANCELLED, FinanceObligation::STATUS_VOIDED, FinanceObligation::STATUS_SUPERSEDED],
                true,
            ));

        return new MoneyItemStatusContext(
            position: $position,
            dngHoldingThisItem: $holding,
            dngNeedsReview: $needsReview,
            obligationCancelled: $obligationCancelled,
            chargeVoid: $chargeVoid,
            dueDate: $invoice->due_date,
        );
    }

    private function paginateCollection(Collection $items, int $perPage, int $page, string $path, array $query): LengthAwarePaginator
    {
        return (new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => $path,
            'query' => $query,
        ]))->withQueryString();
    }
}
