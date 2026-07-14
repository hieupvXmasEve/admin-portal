<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use App\Modules\Finance\Support\Reporting\CollectionProgressCatalog as Catalog;
use App\Modules\Finance\Support\Reporting\CurrentSettlementPositionPresenter;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionAmounts;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionRawEvidence;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-only current Collection Progress lens (FIN-REV-018 / issue 17).
 *
 * Filtering and row grouping stay here, but every settlement component comes
 * from one batch of canonical current Settlement Positions. An invalid target
 * remains a visible row with its stable issue codes; it is never clamped into a
 * trusted report total.
 */
final class ListCollectionProgressQuery
{
    private const NON_BILLABLE_INVOICE_STATUSES = ['cancelled', 'void'];

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly CurrentSettlementPositionPresenter $positionPresenter,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: LengthAwarePaginator, summary: array<string, mixed>, breakdowns: array<string, mixed>}
     */
    public function handle(int $semesterId, array $filters = [], ?CarbonImmutable $asOf = null): array
    {
        $rows = $this->collectRows($semesterId, $filters, $asOf);
        $perPage = in_array((int) ($filters['per_page'] ?? 20), [20, 50, 100], true)
            ? (int) $filters['per_page']
            : 20;
        $page = max(1, (int) ($filters['page'] ?? 1));

        return [
            'rows' => new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            ),
            'summary' => app(GetCollectionProgressSummaryQuery::class)->fromRows($rows),
            'breakdowns' => app(GetCollectionProgressSummaryQuery::class)->breakdownsFromRows($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function collectRows(int $semesterId, array $filters = [], ?CarbonImmutable $asOf = null): Collection
    {
        $campusId = app()->bound('campus') ? (int) app('campus')->id : null;
        $invoices = $this->loadInvoices($semesterId, $campusId, $filters, $asOf);
        $byStudent = $invoices->groupBy('student_id');
        $unappliedByStudent = $asOf === null
            ? $this->unappliedByStudent($byStudent->keys()->map(fn (mixed $id): int => (int) $id)->all())
            : [];
        $positionsByInvoice = $this->positionsForInvoices($invoices, $filters, $asOf);

        $rows = $byStudent->map(function (Collection $studentInvoices, mixed $studentId) use ($semesterId, $unappliedByStudent, $positionsByInvoice, $asOf): ?array {
            $student = $studentInvoices->first()?->student;
            if (! $student instanceof Student) {
                return null;
            }

            $positions = $studentInvoices
                ->map(fn (StudentInvoice $invoice): ?SettlementPosition => $positionsByInvoice[(int) $invoice->id] ?? null)
                ->all();

            return $this->buildRow(
                $student,
                $studentInvoices,
                $positions,
                $semesterId,
                (float) ($unappliedByStudent[(int) $studentId] ?? 0.0),
                $asOf,
            );
        })->filter()->values();

        return $this->sortRows($this->applyComputedFilters($rows, $filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, StudentInvoice>
     */
    private function loadInvoices(
        int $semesterId,
        ?int $campusId,
        array $filters,
        ?CarbonImmutable $asOf = null,
    ): Collection {
        return StudentInvoice::query()
            ->with(['student.program', 'invoiceLines.charge'])
            ->where('semester_id', $semesterId)
            ->when($asOf === null, fn (Builder $query) => $query->whereNotIn('status', self::NON_BILLABLE_INVOICE_STATUSES))
            ->when($campusId !== null, fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId),
            ))
            ->whereHas('student', fn (Builder $studentQuery) => $this->applyStudentAttributeFilters($studentQuery, $filters))
            ->when(
                ! empty($filters['fee_type']) && $filters['fee_type'] !== 'all',
                fn (Builder $query) => $query->whereHas(
                    'invoiceLines.charge',
                    fn (Builder $chargeQuery) => $chargeQuery->where('charge_type', (string) $filters['fee_type']),
                ),
            )
            ->get();
    }

    /**
     * The reader receives one exact scope per invoice. Fee-type filtering is
     * applied to the target line set before reading, so a filter never changes
     * the canonical formula or accidentally includes another fee type.
     *
     * @param  Collection<int, StudentInvoice>  $invoices
     * @param  array<string, mixed>  $filters
     * @return array<int, SettlementPosition>
     */
    private function positionsForInvoices(Collection $invoices, array $filters, ?CarbonImmutable $asOf = null): array
    {
        $feeType = (string) ($filters['fee_type'] ?? 'all');
        $scopes = [];

        foreach ($invoices as $invoice) {
            if ($feeType !== 'all' && $feeType !== '') {
                $lineIds = $invoice->invoiceLines
                    ->filter(fn ($line): bool => $line->charge?->charge_type === $feeType)
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all();

                $scopes[] = SettlementPositionScope::payableLines($lineIds, $asOf);

                continue;
            }

            $scopes[] = SettlementPositionScope::invoice((int) $invoice->id, $asOf);
        }

        if ($scopes === []) {
            return [];
        }

        $positions = $this->settlementPositionReader->batch($scopes);
        $indexed = [];
        foreach ($invoices->values() as $index => $invoice) {
            if (isset($positions[$index])) {
                $indexed[(int) $invoice->id] = $positions[$index];
            }
        }

        return $indexed;
    }

    /**
     * @param  Collection<int, StudentInvoice>  $studentInvoices
     * @param  list<SettlementPosition>  $positions
     * @return array<string, mixed>
     */
    private function buildRow(
        Student $student,
        Collection $studentInvoices,
        array $positions,
        int $semesterId,
        float $unapplied,
        ?CarbonImmutable $asOf = null,
    ): array {
        $raw = $this->zeroMoneySet();
        $amounts = $this->zeroMoneySet();
        $invalid = false;
        $issues = [];
        $feeTypes = [];
        $maxDaysOverdue = 0;
        $focusInvoiceId = null;
        $focusRemaining = -1.0;
        $settlementBreakdown = [];
        $settlementVersion = null;

        foreach ($studentInvoices->values() as $index => $invoice) {
            $position = $positions[$index] ?? null;
            if (! $position instanceof SettlementPosition) {
                continue;
            }

            $settlementVersion ??= $position->snapshot_version;
            $raw = $this->addMoneySet($raw, $position->raw_evidence);
            $settlementBreakdown[] = [
                'invoice_id' => (int) $invoice->id,
                'due_date' => $invoice->due_date?->toIso8601String(),
                'position' => $this->positionPresenter->present($position),
            ];

            if (! $position->isValid() || $position->amounts === null) {
                $invalid = true;
                foreach ($position->issues as $issue) {
                    $issues[$issue->code] = $this->positionPresenter->issues([$issue])[0];
                }

                continue;
            }

            $amounts = $this->addMoneySet($amounts, $position->amounts);
            $remaining = (float) $position->amounts->remaining->amount;

            if ($remaining > $focusRemaining) {
                $focusRemaining = $remaining;
                $focusInvoiceId = (int) $invoice->id;
            }

            $reportAt = $asOf ?? CarbonImmutable::now();
            if ($invoice->due_date !== null && $invoice->due_date->lessThan($reportAt) && $remaining > Catalog::TOLERANCE) {
                $maxDaysOverdue = max($maxDaysOverdue, (int) floor($invoice->due_date->diffInDays($reportAt)));
            }

            foreach ($position->payable_line_breakdown as $linePosition) {
                if (! $linePosition->isValid() || $linePosition->amounts === null || $linePosition->fee_type === null) {
                    continue;
                }

                $lineAmounts = $linePosition->amounts;
                $type = $linePosition->fee_type;
                $feeTypes[$type] ??= $this->numericMoneySet();
                $feeTypes[$type]['gross'] += (float) $lineAmounts->gross->amount;
                $feeTypes[$type]['discount'] += (float) $lineAmounts->discount->amount;
                $feeTypes[$type]['cash'] += (float) $lineAmounts->cash->amount;
                $feeTypes[$type]['credit'] += (float) $lineAmounts->credit->amount;
                $feeTypes[$type]['billed'] += (float) $lineAmounts->netDue()->amount;
                $feeTypes[$type]['paid'] += (float) $lineAmounts->cash->amount;
                $feeTypes[$type]['outstanding'] += (float) $lineAmounts->remaining->amount;
            }
        }

        $isValid = ! $invalid && $settlementBreakdown !== [];
        $gross = $this->numeric($amounts['gross']);
        $discount = $this->numeric($amounts['discount']);
        $cash = $this->numeric($amounts['cash']);
        $credit = $this->numeric($amounts['credit']);
        $remaining = $this->numeric($amounts['remaining']);
        $billed = $isValid ? $this->numeric($this->netDue($amounts)) : null;
        $paid = $isValid ? $cash : null;
        $outstanding = $isValid ? $remaining : null;
        $overdue = $isValid && $remaining > Catalog::TOLERANCE && $maxDaysOverdue > 0 ? $remaining : ($isValid ? 0.0 : null);
        $balanceState = $isValid
            ? $this->primaryBalanceState($cash, $credit, (float) $outstanding, (float) $overdue)
            : Catalog::STATE_INVALID;

        return [
            'row_key' => 'student-'.$student->id.'-sem-'.$semesterId,
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_id,
                'full_name' => $student->full_name,
                'status' => $student->status,
                'status_label' => $student->status_label,
            ],
            'program_code' => $student->program?->code,
            'intake_semester_id' => $student->intake_semester_id,
            'cohort' => $student->intake,
            'semester_id' => $semesterId,
            'invoice_count' => $studentInvoices->count(),
            'fee_types' => array_keys($feeTypes),
            'fee_type_breakdown' => $this->roundMoneySets($feeTypes),
            'valid' => $isValid,
            'settlement_state' => $isValid ? $balanceState : SettlementPosition::STATE_INVALID,
            'settlement_version' => $settlementVersion,
            'settlement_breakdown' => $settlementBreakdown,
            'settlement_raw_evidence' => $this->positionPresenter->rawEvidence(new SettlementPositionRawEvidence(
                gross: $raw['gross'],
                discount: $raw['discount'],
                cash: $raw['cash'],
                credit: $raw['credit'],
                remaining: $raw['remaining'],
                reversed_discount_residue: Money::zero(),
                inactive_credit_residue: Money::zero(),
            )),
            'settlement_issue_codes' => array_values(array_keys($issues)),
            'settlement_issues' => array_values($issues),
            'gross' => $isValid ? $gross : null,
            'discount' => $isValid ? $discount : null,
            'credit' => $isValid ? $credit : null,
            'billed' => $billed,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'overdue' => $overdue,
            'overpaid' => null,
            'unapplied' => round($unapplied, 2),
            'has_unapplied' => $unapplied > Catalog::TOLERANCE,
            'collection_rate' => $isValid && $billed > Catalog::TOLERANCE ? round(min(1.0, $cash / $billed), 4) : null,
            'balance_state' => $balanceState,
            'balance_state_label' => Catalog::balanceStateLabel($balanceState),
            'aging_bucket' => $isValid ? Catalog::classifyAging($maxDaysOverdue) : Catalog::BUCKET_NOT_DUE,
            'aging_bucket_label' => $isValid ? Catalog::agingBucketLabel(Catalog::classifyAging($maxDaysOverdue)) : 'Cần kiểm tra',
            'max_days_overdue' => $isValid ? $maxDaysOverdue : null,
            'is_lifecycle_exception' => LifecycleDueItemPredicate::isLifecycleException($student),
            'drilldowns' => [
                'student_360_focus' => $focusInvoiceId !== null ? 'invoice:'.$focusInvoiceId : null,
                'lookup_invoice_id' => $focusInvoiceId,
            ],
        ];
    }

    /** @return array{gross:Money,discount:Money,cash:Money,credit:Money,remaining:Money} */
    private function zeroMoneySet(): array
    {
        return [
            'gross' => Money::zero(),
            'discount' => Money::zero(),
            'cash' => Money::zero(),
            'credit' => Money::zero(),
            'remaining' => Money::zero(),
        ];
    }

    /**
     * @param  array{gross:Money,discount:Money,cash:Money,credit:Money,remaining:Money}  $current
     * @param  SettlementPosition|SettlementPositionRawEvidence|SettlementPositionAmounts  $source
     * @return array{gross:Money,discount:Money,cash:Money,credit:Money,remaining:Money}
     */
    private function addMoneySet(array $current, object $source): array
    {
        $evidence = $source instanceof SettlementPosition ? $source->raw_evidence : $source;

        return [
            'gross' => $current['gross']->add($evidence->gross),
            'discount' => $current['discount']->add($evidence->discount),
            'cash' => $current['cash']->add($evidence->cash),
            'credit' => $current['credit']->add($evidence->credit),
            'remaining' => $current['remaining']->add($evidence->remaining),
        ];
    }

    /** @return array<string, float> */
    private function numericMoneySet(): array
    {
        return ['gross' => 0.0, 'discount' => 0.0, 'cash' => 0.0, 'credit' => 0.0, 'billed' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0];
    }

    private function numeric(Money $money): float
    {
        return (float) $money->amount;
    }

    /** @param array{gross:Money,discount:Money,cash:Money,credit:Money,remaining:Money} $amounts */
    private function netDue(array $amounts): Money
    {
        return (new SettlementPositionAmounts(
            gross: $amounts['gross'],
            discount: $amounts['discount'],
            cash: $amounts['cash'],
            credit: $amounts['credit'],
            remaining: $amounts['remaining'],
        ))->netDue();
    }

    /** @param array<string, array<string, float>> $sets */
    private function roundMoneySets(array $sets): array
    {
        return array_map(static fn (array $set): array => array_map(
            static fn (float $amount): float => round($amount, 2),
            $set,
        ), $sets);
    }

    private function primaryBalanceState(float $cash, float $credit, float $outstanding, float $overdue): string
    {
        if ($overdue > Catalog::TOLERANCE) {
            return Catalog::STATE_OVERDUE;
        }

        if ($outstanding > Catalog::TOLERANCE) {
            return $cash > Catalog::TOLERANCE || $credit > Catalog::TOLERANCE
                ? Catalog::STATE_PARTIALLY_PAID
                : Catalog::STATE_UNPAID;
        }

        return Catalog::STATE_PAID;
    }

    /** @param list<int> $studentIds */
    private function unappliedByStudent(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $payments = Payment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', Payment::STATUS_COMPLETED)
            ->get(['id', 'student_id', 'amount']);

        if ($payments->isEmpty()) {
            return [];
        }

        $appliedByPayment = PaymentApplication::query()
            ->whereIn('payment_id', $payments->pluck('id'))
            ->selectRaw('payment_id, SUM(amount) as applied')
            ->groupBy('payment_id')
            ->pluck('applied', 'payment_id');
        $disposedByPayment = PaymentSurplusDisposition::query()
            ->whereIn('payment_id', $payments->pluck('id'))
            ->whereIn('type', [PaymentSurplusDisposition::TYPE_REFUND, PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT])
            ->selectRaw('payment_id, SUM(amount) as amount')
            ->groupBy('payment_id')
            ->pluck('amount', 'payment_id');

        $unapplied = [];
        foreach ($payments as $payment) {
            $applied = (float) ($appliedByPayment[$payment->id] ?? 0.0);
            $disposed = (float) ($disposedByPayment[$payment->id] ?? 0.0);
            $remaining = max(0.0, (float) $payment->amount - $applied - $disposed);
            $unapplied[(int) $payment->student_id] = ($unapplied[(int) $payment->student_id] ?? 0.0) + $remaining;
        }

        return $unapplied;
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function applyComputedFilters(Collection $rows, array $filters): Collection
    {
        $balanceState = (string) ($filters['balance_state'] ?? 'all');
        $agingBucket = (string) ($filters['aging_bucket'] ?? 'all');

        return $rows
            ->when($balanceState !== 'all' && $balanceState !== '', fn (Collection $collection) => $balanceState === Catalog::STATE_UNAPPLIED
                ? $collection->where('has_unapplied', true)
                : $collection->where('balance_state', $balanceState))
            ->when($agingBucket !== 'all' && $agingBucket !== '', fn (Collection $collection) => $collection->where('aging_bucket', $agingBucket))
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function sortRows(Collection $rows): Collection
    {
        return $rows->sort(fn (array $a, array $b) => [
            $a['balance_state'] === Catalog::STATE_INVALID ? 0 : 1,
            (float) ($b['overdue'] ?? 0),
            (float) ($b['outstanding'] ?? 0),
            $a['student']['student_code'],
        ] <=> [
            $b['balance_state'] === Catalog::STATE_INVALID ? 0 : 1,
            (float) ($a['overdue'] ?? 0),
            (float) ($a['outstanding'] ?? 0),
            $b['student']['student_code'],
        ])->values();
    }

    public function filterOptions(int $semesterId): array
    {
        $campusId = app()->bound('campus') ? (int) app('campus')->id : null;
        $studentIds = StudentInvoice::query()
            ->where('semester_id', $semesterId)
            ->whereNotIn('status', self::NON_BILLABLE_INVOICE_STATUSES)
            ->when($campusId !== null, fn (Builder $query) => $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId)))
            ->distinct()
            ->pluck('student_id');
        $students = Student::query()->with('program')->whereIn('id', $studentIds)->get();

        return [
            'programs' => Program::query()->whereIn('id', $students->pluck('program_id')->filter()->unique())->orderBy('code')->get(['id', 'code', 'name'])->map(fn (Program $program) => ['value' => $program->id, 'label' => $program->code.' — '.$program->name])->values()->all(),
            'intakes' => Semester::query()->whereIn('id', $students->pluck('intake_semester_id')->filter()->unique())->orderByDesc('start_date')->get(['id', 'name'])->map(fn (Semester $semester) => ['value' => $semester->id, 'label' => $semester->name])->values()->all(),
            'cohorts' => $students->pluck('intake')->filter()->unique()->sort()->values()->map(fn (int $intake) => ['value' => $intake, 'label' => 'Intake '.$intake])->all(),
            'fee_types' => $this->scopedFeeTypes($semesterId, $campusId)->map(fn (string $type) => ['value' => $type, 'label' => $type])->all(),
            'balance_states' => collect(Catalog::balanceStates())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'aging_buckets' => collect(Catalog::agingBuckets())->map(fn (array $meta, string $value) => ['value' => $value, 'label' => $meta['label']])->values()->all(),
            'student_statuses' => $students->pluck('status')->filter()->unique()->values()->map(fn (string $status) => ['value' => $status, 'label' => (new Student(['status' => $status]))->status_label])->all(),
            'semester_id' => $semesterId,
        ];
    }

    /** @return Collection<int, string> */
    private function scopedFeeTypes(int $semesterId, ?int $campusId): Collection
    {
        return StudentInvoice::query()
            ->where('semester_id', $semesterId)
            ->whereNotIn('status', self::NON_BILLABLE_INVOICE_STATUSES)
            ->when($campusId !== null, fn (Builder $query) => $query->whereHas('student', fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId)))
            ->with('invoiceLines.charge')
            ->get()
            ->flatMap(fn (StudentInvoice $invoice) => $invoice->invoiceLines->map(fn ($line) => $line->charge?->charge_type))
            ->filter()
            ->unique()
            ->values();
    }

    /** @param Builder<Student> $query */
    private function applyStudentAttributeFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['program_id']) && $filters['program_id'] !== 'all') {
            $query->where('program_id', (int) $filters['program_id']);
        }
        if (! empty($filters['intake_semester_id']) && $filters['intake_semester_id'] !== 'all') {
            $query->where('intake_semester_id', (int) $filters['intake_semester_id']);
        }
        if (! empty($filters['cohort']) && $filters['cohort'] !== 'all') {
            $query->where('intake', (int) $filters['cohort']);
        }
        if (! empty($filters['student_status']) && $filters['student_status'] !== 'all') {
            $query->where('status', (string) $filters['student_status']);
        }
        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(fn (Builder $searchQuery) => $searchQuery->where('full_name', 'like', "%{$search}%")->orWhere('student_id', 'like', "%{$search}%"));
        }

        return $query;
    }
}
