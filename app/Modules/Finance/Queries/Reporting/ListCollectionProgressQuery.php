<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use App\Modules\Finance\Support\Reporting\CollectionProgressCatalog as Catalog;
use App\Modules\Finance\Support\Reporting\CurrentSettlementPositionPresenter;
use App\Modules\Finance\Support\Reporting\UnappliedCashReader;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionAmounts;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionRawEvidence;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\ProgramEnrollmentSummary;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use App\Shared\Support\Academic\StudentLifecycleStatusPresenter;
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
        private readonly StudentReferenceReader $studentReferences,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly UnappliedCashReader $unappliedCashReader,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: LengthAwarePaginator, summary: array<string, mixed>, breakdowns: array<string, mixed>}
     */
    public function handle(int $semesterId, array $filters = [], ?CarbonImmutable $asOf = null): array
    {
        $rows = $this->collectRows($semesterId, $filters, $asOf);
        $requestedPerPage = (int) ($filters['per_page'] ?? 20);
        $perPage = in_array($requestedPerPage, [20, 50, 100], true) ? $requestedPerPage : 20;
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
        $invoices = $this->loadInvoices($semesterId, $filters, $asOf);
        $studentIds = $invoices->pluck('student_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $students = $this->studentReferences->findMany($studentIds);
        $enrollments = $this->programEnrollments->forStudentIds(array_keys($students));
        $invoices = $invoices
            ->filter(fn (StudentInvoice $invoice): bool => isset($students[(int) $invoice->student_id])
                && ($campusId === null || $students[(int) $invoice->student_id]->campusId === $campusId)
                && $this->matchesStudentFilters(
                    $students[(int) $invoice->student_id],
                    $enrollments[(int) $invoice->student_id] ?? null,
                    $filters,
                ))
            ->values();
        $byStudent = $invoices->groupBy('student_id');
        $unappliedByStudent = $asOf === null
            ? $this->unappliedCashReader->unappliedByStudent($byStudent->keys()->map(fn (mixed $id): int => (int) $id)->all())
            : [];
        $positionsByInvoice = $this->positionsForInvoices($invoices, $filters, $asOf);

        $rows = $byStudent->map(function (Collection $studentInvoices, mixed $studentId) use ($semesterId, $unappliedByStudent, $positionsByInvoice, $asOf, $students, $enrollments): ?array {
            $student = $students[(int) $studentId] ?? null;
            if (! $student instanceof StudentReference) {
                return null;
            }

            $positions = $studentInvoices
                ->map(fn (StudentInvoice $invoice): ?SettlementPosition => $positionsByInvoice[(int) $invoice->id] ?? null)
                ->all();

            return $this->buildRow(
                $student,
                $enrollments[(int) $studentId] ?? null,
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
        array $filters,
        ?CarbonImmutable $asOf = null,
    ): Collection {
        return StudentInvoice::query()
            ->with(['invoiceLines.charge'])
            ->where('semester_id', $semesterId)
            ->when($asOf === null, fn (Builder $query) => $query->whereNotIn('status', self::NON_BILLABLE_INVOICE_STATUSES))
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
        StudentReference $student,
        ?ProgramEnrollmentSummary $enrollment,
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
        // students.status is a legacy column progression transitions don't
        // write back to; prefer the live program_enrollments projection.
        $liveStatus = $enrollment?->legacyCompatibleStatus() ?? (string) $student->status;

        return [
            'row_key' => 'student-'.$student->id.'-sem-'.$semesterId,
            'student' => [
                'id' => $student->id,
                'student_code' => $student->studentCode,
                'full_name' => $student->fullName,
                'status' => $liveStatus,
                'status_label' => StudentLifecycleStatusPresenter::label($liveStatus),
            ],
            'program_code' => $student->programCode,
            'intake_semester_id' => $student->intakeSemesterId,
            'cohort' => $student->cohort,
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
            'is_lifecycle_exception' => LifecycleDueItemPredicate::isLifecycleExceptionStatus($liveStatus),
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
            ->distinct()
            ->pluck('student_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $students = collect($this->studentReferences->findMany($studentIds))
            ->filter(fn (StudentReference $student): bool => $campusId === null || $student->campusId === $campusId);
        $enrollments = $this->programEnrollments->forStudentIds($students->keys()->all());
        $intakeIds = $students
            ->map(fn (StudentReference $student): ?int => $student->intakeSemesterId)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $periods = $this->academicPeriods->findMany($intakeIds);

        return [
            'programs' => $students
                ->filter(fn (StudentReference $student): bool => $student->programId !== null)
                ->unique(fn (StudentReference $student): ?int => $student->programId)
                ->sortBy(fn (StudentReference $student): string => (string) $student->programCode)
                ->map(fn (StudentReference $student): array => [
                    'value' => $student->programId,
                    'label' => $student->programCode.' — '.$student->programName,
                ])
                ->values()
                ->all(),
            'intakes' => collect($periods)
                ->sortByDesc(fn ($period) => $period->start_date)
                ->map(fn ($period): array => ['value' => $period->id, 'label' => $period->name])
                ->values()
                ->all(),
            'cohorts' => $students->pluck('cohort')->filter()->unique()->sort()->values()->map(fn (int $intake) => ['value' => $intake, 'label' => 'Intake '.$intake])->all(),
            'fee_types' => $this->scopedFeeTypes($semesterId, $campusId)->map(fn (string $type) => ['value' => $type, 'label' => $type])->all(),
            'balance_states' => collect(Catalog::balanceStates())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'aging_buckets' => collect(Catalog::agingBuckets())->map(fn (array $meta, string $value) => ['value' => $value, 'label' => $meta['label']])->values()->all(),
            'student_statuses' => $students
                ->map(fn (StudentReference $student): ?string => $enrollments[$student->id]?->legacyCompatibleStatus() ?? $student->status)
                ->filter(fn (?string $status): bool => $status !== null)
                ->unique()
                ->map(fn (string $status): array => ['value' => $status, 'label' => StudentLifecycleStatusPresenter::label($status)])
                ->values()
                ->all(),
            'semester_id' => $semesterId,
        ];
    }

    /** @return Collection<int, string> */
    private function scopedFeeTypes(int $semesterId, ?int $campusId): Collection
    {
        $studentIds = $campusId === null ? [] : $this->studentReferences->idsForCampus($campusId);

        return StudentInvoice::query()
            ->where('semester_id', $semesterId)
            ->whereNotIn('status', self::NON_BILLABLE_INVOICE_STATUSES)
            ->when($campusId !== null, fn (Builder $query) => $query->whereIn('student_id', $studentIds))
            ->with('invoiceLines.charge')
            ->get()
            ->flatMap(fn (StudentInvoice $invoice) => $invoice->invoiceLines->map(fn ($line) => $line->charge?->charge_type))
            ->filter()
            ->unique()
            ->values();
    }

    /** @param array<string, mixed> $filters */
    private function matchesStudentFilters(
        StudentReference $student,
        ?ProgramEnrollmentSummary $enrollment,
        array $filters,
    ): bool {
        if (! empty($filters['program_id']) && $filters['program_id'] !== 'all'
            && $student->programId !== (int) $filters['program_id']) {
            return false;
        }
        if (! empty($filters['intake_semester_id']) && $filters['intake_semester_id'] !== 'all'
            && $student->intakeSemesterId !== (int) $filters['intake_semester_id']) {
            return false;
        }
        if (! empty($filters['cohort']) && $filters['cohort'] !== 'all'
            && $student->cohort !== (int) $filters['cohort']) {
            return false;
        }
        if (! empty($filters['student_status']) && $filters['student_status'] !== 'all'
            && ($enrollment?->legacyCompatibleStatus() ?? $student->status) !== (string) $filters['student_status']) {
            return false;
        }
        if (! empty($filters['search'])) {
            $search = mb_strtolower((string) $filters['search']);
            if (! str_contains(mb_strtolower($student->fullName.' '.$student->studentCode), $search)) {
                return false;
            }
        }

        return true;
    }
}
