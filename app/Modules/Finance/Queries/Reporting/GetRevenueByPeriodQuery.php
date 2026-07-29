<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Models\Campus;
use App\Models\Semester;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Reporting\RevenuePeriodBucket;
use App\Modules\Finance\Support\Reporting\UnappliedCashReader;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

/**
 * Multi-semester revenue rollup. Always school-wide — deliberately ignores
 * `app('campus')` (plan.md "Bẫy phải tránh") — and reads through the
 * canonical Settlement Position reader per payable line so figures match
 * Collection Progress structurally instead of by coincidence. See plan.md
 * "Quyết định kiến trúc" for why this does not run a parallel SQL aggregate.
 */
final class GetRevenueByPeriodQuery
{
    private const NON_BILLABLE_INVOICE_STATUSES = ['cancelled', 'void'];

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly StudentReferenceReader $studentReferences,
        private readonly UnappliedCashReader $unappliedCashReader,
    ) {}

    /**
     * @param  array{semester_ids?: list<int>, campus_id?: int|null, fee_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters = []): array
    {
        $semesterIds = array_map(static fn (int|string $id): int => (int) $id, $filters['semester_ids'] ?? []);
        $campusFilter = isset($filters['campus_id']) ? (int) $filters['campus_id'] : null;
        $feeTypeFilter = in_array($filters['fee_type'] ?? null, [null, '', 'all'], true) ? null : (string) $filters['fee_type'];
        $campusLabels = Campus::query()->pluck('name', 'id');

        [$buckets, $unresolvedStudentCount] = $this->buildBuckets($semesterIds, $campusFilter, $feeTypeFilter);

        $rows = $this->rowsFromBuckets($buckets, $campusLabels);
        $totals = $this->totalsFromBuckets($buckets);

        return [
            'rows' => $rows,
            'totals' => $totals,
            'breakdowns' => [
                'by_campus' => $this->globalBreakdown($buckets, dimension: 'campus', labels: $campusLabels),
                'by_fee_type' => $this->globalBreakdown($buckets, dimension: 'fee_type', labels: collect()),
            ],
            'unattributed' => $this->unattributedPayload(),
            'unresolved_student_count' => $unresolvedStudentCount,
        ];
    }

    /**
     * @param  list<int>  $semesterIds
     * @return array{0: array<int, array<int, array<string, RevenuePeriodBucket>>>, 1: int}
     */
    private function buildBuckets(array $semesterIds, ?int $campusFilter, ?string $feeTypeFilter): array
    {
        $lineContext = $this->loadLineContext($semesterIds);
        if ($lineContext === []) {
            return [[], 0];
        }

        $studentIds = array_values(array_unique(array_column($lineContext, 'student_id')));
        $students = $this->studentReferences->findMany($studentIds);
        $lineIds = array_keys($lineContext);

        // ponytail: đọc qua SettlementPositionReader thay vì SQL aggregate — ~1k invoice_lines
        // hiện tại nên chi phí không đáng kể, và tránh bản cài đặt thứ hai của công thức
        // settlement (11 validity gate) lệch âm thầm khỏi Collection Progress.
        // Nếu vượt ~50k line: thay bước dưới bằng CTE gộp cash/discount/credit theo line,
        // nhưng phải port đủ validity gate và thêm test đối chiếu với reader.
        $positions = $this->settlementPositionReader->batch(array_map(
            static fn (int $id): SettlementPositionScope => SettlementPositionScope::payableLine($id),
            $lineIds,
        ));

        $buckets = [];
        $unresolvedStudentCount = 0;

        foreach ($lineIds as $index => $lineId) {
            $context = $lineContext[$lineId];
            $student = $students[$context['student_id']] ?? null;
            if ($student === null) {
                $unresolvedStudentCount++;

                continue;
            }

            if ($campusFilter !== null && $student->campusId !== $campusFilter) {
                continue;
            }

            /** @var SettlementPosition $position */
            $position = $positions[$index];
            $feeType = $position->fee_type ?? '—';
            if ($feeTypeFilter !== null && $feeType !== $feeTypeFilter) {
                continue;
            }

            $semesterId = $context['semester_id'];
            $campusId = $student->campusId;
            $existing = $buckets[$semesterId][$campusId][$feeType] ?? RevenuePeriodBucket::empty();
            $buckets[$semesterId][$campusId][$feeType] = $existing->withPosition($position);
        }

        return [$buckets, $unresolvedStudentCount];
    }

    /**
     * @param  list<int>  $semesterIds
     * @return array<int, array{semester_id: int, student_id: int}>
     */
    private function loadLineContext(array $semesterIds): array
    {
        return InvoiceLine::query()
            ->join('student_invoices', 'student_invoices.id', '=', 'invoice_lines.invoice_id')
            ->whereNotIn('student_invoices.status', self::NON_BILLABLE_INVOICE_STATUSES)
            ->when($semesterIds !== [], fn ($query) => $query->whereIn('student_invoices.semester_id', $semesterIds))
            ->get(['invoice_lines.id', 'student_invoices.semester_id', 'student_invoices.student_id'])
            ->mapWithKeys(fn ($row): array => [(int) $row->id => [
                'semester_id' => (int) $row->semester_id,
                'student_id' => (int) $row->student_id,
            ]])
            ->all();
    }

    /**
     * @param  array<int, array<int, array<string, RevenuePeriodBucket>>>  $buckets
     * @param  \Illuminate\Support\Collection<int, string>  $campusLabels
     * @return list<array<string, mixed>>
     */
    private function rowsFromBuckets(array $buckets, $campusLabels): array
    {
        if ($buckets === []) {
            return [];
        }

        $semesterIds = array_keys($buckets);
        $semesters = Semester::query()->whereIn('id', $semesterIds)->get(['id', 'name', 'start_date'])->keyBy('id');

        usort($semesterIds, static function (int $a, int $b) use ($semesters): int {
            $startA = $semesters[$a]?->start_date;
            $startB = $semesters[$b]?->start_date;

            return ($startB?->timestamp ?? 0) <=> ($startA?->timestamp ?? 0);
        });

        $rows = [];
        foreach ($semesterIds as $semesterId) {
            $periodTotal = RevenuePeriodBucket::empty();
            $byCampus = [];
            $byFeeType = [];

            foreach ($buckets[$semesterId] as $campusId => $feeTypeBuckets) {
                foreach ($feeTypeBuckets as $feeType => $bucket) {
                    $periodTotal = $periodTotal->merge($bucket);
                    $byCampus[$campusId] = ($byCampus[$campusId] ?? RevenuePeriodBucket::empty())->merge($bucket);
                    $byFeeType[$feeType] = ($byFeeType[$feeType] ?? RevenuePeriodBucket::empty())->merge($bucket);
                }
            }

            $semester = $semesters[$semesterId] ?? null;
            $rows[] = [
                'semester_id' => $semesterId,
                'semester_name' => $semester?->name ?? (string) $semesterId,
                'start_date' => $semester?->start_date?->toDateString(),
                ...$periodTotal->toArray(),
                'growth_pct' => null,
                'by_campus' => $this->presentBreakdown($byCampus, $campusLabels),
                'by_fee_type' => $this->presentBreakdown($byFeeType, collect()),
            ];
        }

        return $this->applyGrowth($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function applyGrowth(array $rows): array
    {
        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            $previous = $rows[$i + 1] ?? null;
            $rows[$i]['growth_pct'] = ($previous !== null && $previous['net_billed'] > 0)
                ? round(($rows[$i]['net_billed'] - $previous['net_billed']) / $previous['net_billed'], 4)
                : null;
        }

        return $rows;
    }

    /**
     * @param  array<int|string, RevenuePeriodBucket>  $bucketsByKey
     * @param  \Illuminate\Support\Collection<int, string>  $labels
     * @return list<array<string, mixed>>
     */
    private function presentBreakdown(array $bucketsByKey, $labels): array
    {
        $items = [];
        foreach ($bucketsByKey as $key => $bucket) {
            $items[] = [
                'key' => (string) $key,
                'label' => $labels[$key] ?? (string) $key,
                ...$bucket->toArray(),
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['outstanding'] <=> $a['outstanding']);

        return $items;
    }

    /**
     * @param  array<int, array<int, array<string, RevenuePeriodBucket>>>  $buckets
     * @param  \Illuminate\Support\Collection<int, string>  $labels
     * @return list<array<string, mixed>>
     */
    private function globalBreakdown(array $buckets, string $dimension, $labels): array
    {
        $merged = [];
        foreach ($buckets as $campusBuckets) {
            foreach ($campusBuckets as $campusId => $feeTypeBuckets) {
                foreach ($feeTypeBuckets as $feeType => $bucket) {
                    $key = $dimension === 'campus' ? $campusId : $feeType;
                    $merged[$key] = ($merged[$key] ?? RevenuePeriodBucket::empty())->merge($bucket);
                }
            }
        }

        return $this->presentBreakdown($merged, $labels);
    }

    /**
     * @param  array<int, array<int, array<string, RevenuePeriodBucket>>>  $buckets
     * @return array<string, mixed>
     */
    private function totalsFromBuckets(array $buckets): array
    {
        $total = RevenuePeriodBucket::empty();
        foreach ($buckets as $campusBuckets) {
            foreach ($campusBuckets as $feeTypeBuckets) {
                foreach ($feeTypeBuckets as $bucket) {
                    $total = $total->merge($bucket);
                }
            }
        }

        return $total->toArray();
    }

    /** @return array<string, mixed> */
    public function filterOptions(): array
    {
        $semesterIds = StudentInvoice::query()
            ->whereNotIn('status', self::NON_BILLABLE_INVOICE_STATUSES)
            ->distinct()
            ->pluck('semester_id');

        $semesters = Semester::query()
            ->whereIn('id', $semesterIds)
            ->orderByDesc('start_date')
            ->get(['id', 'name']);

        $feeTypes = InvoiceLine::query()
            ->join('student_invoices', 'student_invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('finance_charges', 'finance_charges.id', '=', 'invoice_lines.charge_id')
            ->whereNotIn('student_invoices.status', self::NON_BILLABLE_INVOICE_STATUSES)
            ->distinct()
            ->pluck('finance_charges.charge_type')
            ->filter()
            ->values();

        return [
            'semesters' => $semesters->map(fn (Semester $semester): array => ['value' => $semester->id, 'label' => $semester->name])->all(),
            'campuses' => Campus::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Campus $campus): array => ['value' => $campus->id, 'label' => $campus->name])->all(),
            'fee_types' => $feeTypes->map(fn (string $type): array => ['value' => $type, 'label' => $type])->all(),
        ];
    }

    /** @return array{unapplied: float, refund: float, retain_forfeit: float} */
    private function unattributedPayload(): array
    {
        $totals = $this->unappliedCashReader->globalTotals();

        return [
            'unapplied' => (float) $totals['unapplied']->amount,
            'refund' => (float) $totals['refund']->amount,
            'retain_forfeit' => (float) $totals['retain_forfeit']->amount,
        ];
    }
}
