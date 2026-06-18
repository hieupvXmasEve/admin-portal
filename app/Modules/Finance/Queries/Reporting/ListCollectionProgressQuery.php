<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Models\DiscountAllocation;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use App\Modules\Finance\Support\Reporting\CollectionProgressCatalog as Catalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-only Collection Progress lens (FIN-REV-018).
 *
 * Row grain is `student × semester_balance` for the selected Finance semester
 * and the authenticated account campus. Every money figure derives from the
 * canonical settlement/ledger read model (SettlementService) — cached invoice
 * columns are never treated as source of truth here.
 */
class ListCollectionProgressQuery
{
    /** Invoice lifecycle states that carry no payable balance for this lens. */
    private const NON_BILLABLE_INVOICE_STATUSES = ['cancelled', 'void'];

    public function __construct(
        private readonly SettlementService $settlement,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{rows: LengthAwarePaginator, summary: array<string, mixed>, breakdowns: array<string, mixed>}
     */
    public function handle(int $semesterId, array $filters = []): array
    {
        $rows = $this->collectRows($semesterId, $filters);

        $perPage = in_array((int) ($filters['per_page'] ?? 20), [20, 50, 100], true)
            ? (int) $filters['per_page']
            : 20;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageRows = $rows->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pageRows,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );

        $summaryQuery = app(GetCollectionProgressSummaryQuery::class);

        return [
            'rows' => $paginator,
            'summary' => $summaryQuery->fromRows($rows),
            'breakdowns' => $summaryQuery->breakdownsFromRows($rows),
        ];
    }

    /**
     * Build the fully-filtered row collection used by both pagination and the
     * summary/breakdown statistics so the numbers always match the visible scope.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function collectRows(int $semesterId, array $filters = []): Collection
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        $invoices = $this->loadInvoices($semesterId, $campusId, $filters);
        $byStudent = $invoices->groupBy('student_id');
        $unappliedByStudent = $this->unappliedByStudent($byStudent->keys()->all());

        $rows = $byStudent
            ->map(function (Collection $studentInvoices, $studentId) use ($semesterId, $unappliedByStudent): ?array {
                $student = $studentInvoices->first()?->student;
                if ($student === null) {
                    return null;
                }

                return $this->buildRow(
                    $student,
                    $studentInvoices,
                    $semesterId,
                    (float) ($unappliedByStudent[(int) $studentId] ?? 0.0),
                );
            })
            ->filter()
            ->values();

        return $this->sortRows($this->applyComputedFilters($rows, $filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, StudentInvoice>
     */
    private function loadInvoices(int $semesterId, ?int $campusId, array $filters): Collection
    {
        return StudentInvoice::query()
            ->with([
                'student.program',
                'invoiceLines.charge',
                'invoiceLines.paymentApplications',
                'invoiceLines.discountAllocations.invoiceDiscount',
            ])
            ->where('semester_id', $semesterId)
            ->whereNotIn('status', self::NON_BILLABLE_INVOICE_STATUSES)
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
     * @param  Collection<int, StudentInvoice>  $studentInvoices
     * @return array<string, mixed>
     */
    private function buildRow(Student $student, Collection $studentInvoices, int $semesterId, float $unapplied): array
    {
        $billed = 0.0;
        $paid = 0.0;
        $outstanding = 0.0;
        $overdue = 0.0;
        $overpaid = 0.0;
        $maxDaysOverdue = 0;

        /** @var array<string, array{billed: float, paid: float, outstanding: float}> $feeTypes */
        $feeTypes = [];
        $focusInvoice = null;
        $focusRemaining = -1.0;

        foreach ($studentInvoices as $invoice) {
            $summary = $this->summarizeInvoice($invoice);

            $billed += $summary['net'];
            $paid += $summary['paid'];
            $outstanding += $summary['remaining'];
            $overdue += $summary['overdue'];
            $overpaid += $summary['overpaid'];
            $maxDaysOverdue = max($maxDaysOverdue, $summary['days_overdue']);

            foreach ($summary['fee_types'] as $type => $amounts) {
                $feeTypes[$type] ??= ['billed' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0];
                $feeTypes[$type]['billed'] += $amounts['billed'];
                $feeTypes[$type]['paid'] += $amounts['paid'];
                $feeTypes[$type]['outstanding'] += $amounts['outstanding'];
            }

            if ($summary['remaining'] > $focusRemaining) {
                $focusRemaining = $summary['remaining'];
                $focusInvoice = $invoice;
            }
        }

        $balanceState = $this->primaryBalanceState($billed, $paid, $outstanding, $overdue, $overpaid);
        $agingBucket = Catalog::classifyAging($maxDaysOverdue);

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
            'fee_type_breakdown' => $feeTypes,
            'billed' => round($billed, 2),
            'paid' => round($paid, 2),
            'outstanding' => round($outstanding, 2),
            'overdue' => round($overdue, 2),
            'overpaid' => round($overpaid, 2),
            'unapplied' => round($unapplied, 2),
            'has_unapplied' => $unapplied > Catalog::TOLERANCE,
            'collection_rate' => $billed > Catalog::TOLERANCE ? round(min(1.0, $paid / $billed), 4) : null,
            'balance_state' => $balanceState,
            'balance_state_label' => Catalog::balanceStateLabel($balanceState),
            'aging_bucket' => $agingBucket,
            'aging_bucket_label' => Catalog::agingBucketLabel($agingBucket),
            'max_days_overdue' => $maxDaysOverdue,
            'is_lifecycle_exception' => LifecycleDueItemPredicate::isLifecycleException($student),
            'drilldowns' => [
                'student_360_focus' => $focusInvoice !== null ? 'invoice:'.$focusInvoice->id : null,
                'lookup_invoice_id' => $focusInvoice?->id,
            ],
        ];
    }

    /**
     * Derive a single invoice's contribution to the student semester balance from
     * canonical ledger truth. Invoice-level billed/paid/outstanding come from
     * SettlementService::deriveInvoiceSnapshot; overpaid (applied cash beyond net
     * due) and per-fee-type splits are computed from the same loaded ledger rows.
     *
     * @return array{net: float, paid: float, remaining: float, overdue: float, overpaid: float, days_overdue: int, fee_types: array<string, array{billed: float, paid: float, outstanding: float}>}
     */
    private function summarizeInvoice(StudentInvoice $invoice): array
    {
        $snapshot = $this->settlement->deriveInvoiceSnapshot($invoice);
        $net = (float) $snapshot['net'];
        $paid = (float) $snapshot['paid'];
        $remaining = (float) $snapshot['remaining'];

        $rawPaid = 0.0;
        /** @var array<string, array{billed: float, paid: float, outstanding: float}> $feeTypes */
        $feeTypes = [];

        foreach ($invoice->invoiceLines as $line) {
            if (! $this->isBillableActiveLine($line) || (float) $line->amount_snapshot <= 0) {
                continue;
            }

            $lineDiscount = $this->lineDiscount($line);
            $lineNet = max(0.0, (float) $line->amount_snapshot - $lineDiscount);
            $linePaid = max(0.0, (float) $line->paymentApplications->sum('amount'));
            $rawPaid += $linePaid;

            $cappedPaid = min($linePaid, $lineNet);
            $type = $line->charge?->charge_type ?? 'manual_fee';

            $feeTypes[$type] ??= ['billed' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0];
            $feeTypes[$type]['billed'] += $lineNet;
            $feeTypes[$type]['paid'] += $cappedPaid;
            $feeTypes[$type]['outstanding'] += max(0.0, $lineNet - $cappedPaid);
        }

        $isOverdue = $invoice->due_date !== null
            && $invoice->due_date->isPast()
            && $remaining > Catalog::TOLERANCE;
        $daysOverdue = $isOverdue ? (int) floor($invoice->due_date->diffInDays(now())) : 0;

        return [
            'net' => $net,
            'paid' => $paid,
            'remaining' => $remaining,
            'overdue' => $isOverdue ? $remaining : 0.0,
            'overpaid' => max(0.0, $rawPaid - $net),
            'days_overdue' => $daysOverdue,
            'fee_types' => $feeTypes,
        ];
    }

    /**
     * Sum of active (non-reversed) discount allocations on a line, from the
     * already-loaded relation. Mirrors SettlementService::getLineDiscountAmount
     * so the lens never disagrees with the canonical line net due.
     */
    private function lineDiscount(InvoiceLine $line): float
    {
        return max(0.0, (float) $line->discountAllocations
            ->reject(fn (DiscountAllocation $allocation) => ($allocation->invoiceDiscount?->status ?? 'active') === 'reversed')
            ->sum('amount'));
    }

    private function isBillableActiveLine(InvoiceLine $line): bool
    {
        if (($line->status ?? 'active') !== 'active') {
            return false;
        }

        return $line->charge === null || $line->charge->status === 'active';
    }

    private function primaryBalanceState(float $billed, float $paid, float $outstanding, float $overdue, float $overpaid): string
    {
        $tolerance = Catalog::TOLERANCE;

        if ($overpaid > $tolerance) {
            return Catalog::STATE_OVERPAID;
        }

        if ($overdue > $tolerance) {
            return Catalog::STATE_OVERDUE;
        }

        if ($outstanding > $tolerance) {
            return $paid > $tolerance ? Catalog::STATE_PARTIALLY_PAID : Catalog::STATE_UNPAID;
        }

        return Catalog::STATE_PAID;
    }

    /**
     * Student-level available cash: completed payments minus what has been applied
     * across invoice lines. Canonical formula:
     * `payments.amount - SUM(payment_applications.amount)` per payment, summed by
     * student. Two aggregate queries keep this off the per-row hot path.
     *
     * @param  list<int>  $studentIds
     * @return array<int, float>
     */
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

        $unapplied = [];
        foreach ($payments as $payment) {
            $applied = (float) ($appliedByPayment[$payment->id] ?? 0.0);
            $remaining = max(0.0, (float) $payment->amount - $applied);
            $unapplied[(int) $payment->student_id] = ($unapplied[(int) $payment->student_id] ?? 0.0) + $remaining;
        }

        return $unapplied;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyComputedFilters(Collection $rows, array $filters): Collection
    {
        $balanceState = (string) ($filters['balance_state'] ?? 'all');
        $agingBucket = (string) ($filters['aging_bucket'] ?? 'all');

        return $rows
            ->when($balanceState !== 'all' && $balanceState !== '', fn (Collection $collection) => $collection
                ->filter(fn (array $row) => $this->matchesBalanceState($row, $balanceState)))
            ->when($agingBucket !== 'all' && $agingBucket !== '', fn (Collection $collection) => $collection
                ->where('aging_bucket', $agingBucket))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesBalanceState(array $row, string $state): bool
    {
        $tolerance = Catalog::TOLERANCE;

        return match ($state) {
            Catalog::STATE_UNPAID => $row['paid'] <= $tolerance && $row['outstanding'] > $tolerance,
            Catalog::STATE_PARTIALLY_PAID => $row['paid'] > $tolerance && $row['outstanding'] > $tolerance,
            Catalog::STATE_PAID => $row['billed'] > $tolerance && $row['outstanding'] <= $tolerance && $row['overpaid'] <= $tolerance,
            Catalog::STATE_OVERDUE => $row['overdue'] > $tolerance,
            Catalog::STATE_OVERPAID => $row['overpaid'] > $tolerance,
            Catalog::STATE_UNAPPLIED => $row['unapplied'] > $tolerance,
            default => true,
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows): Collection
    {
        return $rows
            ->sort(fn (array $a, array $b) => [$b['overdue'], $b['outstanding'], $a['student']['student_code']]
                <=> [$a['overdue'], $a['outstanding'], $b['student']['student_code']])
            ->values();
    }

    /**
     * Filter dropdown options scoped to students who carry a semester balance.
     *
     * @return array<string, mixed>
     */
    public function filterOptions(int $semesterId): array
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        $studentIds = StudentInvoice::query()
            ->where('semester_id', $semesterId)
            ->whereNotIn('status', self::NON_BILLABLE_INVOICE_STATUSES)
            ->when($campusId !== null, fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $studentQuery) => $studentQuery->where('campus_id', $campusId),
            ))
            ->distinct()
            ->pluck('student_id');

        $students = Student::query()
            ->with('program')
            ->whereIn('id', $studentIds)
            ->get();

        $programs = Program::query()
            ->whereIn('id', $students->pluck('program_id')->filter()->unique())
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Program $program) => [
                'value' => $program->id,
                'label' => $program->code.' — '.$program->name,
            ])
            ->values()
            ->all();

        $intakes = Semester::query()
            ->whereIn('id', $students->pluck('intake_semester_id')->filter()->unique())
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (Semester $semester) => [
                'value' => $semester->id,
                'label' => $semester->name,
            ])
            ->values()
            ->all();

        $cohorts = $students->pluck('intake')->filter()->unique()->sort()->values()
            ->map(fn (int $intake) => ['value' => $intake, 'label' => 'Intake '.$intake])
            ->all();

        $feeTypes = $this->scopedFeeTypes($semesterId, $campusId)
            ->map(fn (string $type) => ['value' => $type, 'label' => $type])
            ->all();

        $studentStatuses = $students->pluck('status')->filter()->unique()->values()
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => (new Student(['status' => $status]))->status_label,
            ])
            ->all();

        return [
            'programs' => $programs,
            'intakes' => $intakes,
            'cohorts' => $cohorts,
            'fee_types' => $feeTypes,
            'balance_states' => collect(Catalog::balanceStates())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'aging_buckets' => collect(Catalog::agingBuckets())
                ->map(fn (array $meta, string $value) => ['value' => $value, 'label' => $meta['label']])
                ->values()
                ->all(),
            'student_statuses' => $studentStatuses,
            'semester_id' => $semesterId,
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function scopedFeeTypes(int $semesterId, ?int $campusId): Collection
    {
        return InvoiceLine::query()
            ->join('finance_charges', 'finance_charges.id', '=', 'invoice_lines.charge_id')
            ->join('student_invoices', 'student_invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('student_invoices.semester_id', $semesterId)
            ->whereNotIn('student_invoices.status', self::NON_BILLABLE_INVOICE_STATUSES)
            ->when($campusId !== null, fn (Builder $query) => $query->whereExists(function ($sub) use ($campusId): void {
                $sub->selectRaw('1')
                    ->from('students')
                    ->whereColumn('students.id', 'student_invoices.student_id')
                    ->where('students.campus_id', $campusId);
            }))
            ->distinct()
            ->pluck('finance_charges.charge_type')
            ->filter()
            ->values();
    }

    /**
     * @param  Builder<Student>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Student>
     */
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
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
