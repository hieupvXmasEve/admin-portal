<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Modules\Finance\Support\Reporting\CollectionProgressCatalog as Catalog;
use Illuminate\Support\Collection;

/**
 * Aggregates Collection Progress rows into summary cards and grouped breakdowns
 * (FIN-REV-018). Operates purely on the already-derived row collection so the
 * statistics always match the visible, filtered worklist.
 */
class GetCollectionProgressSummaryQuery
{
    /**
     * Summary cards: total money figures plus balance-state counts.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function fromRows(Collection $rows): array
    {
        $billed = round((float) $rows->sum('billed'), 2);
        $paid = round((float) $rows->sum('paid'), 2);

        return [
            'student_count' => $rows->count(),
            'billed_total' => $billed,
            'paid_total' => $paid,
            'outstanding_total' => round((float) $rows->sum('outstanding'), 2),
            'overdue_total' => round((float) $rows->sum('overdue'), 2),
            'overpaid_total' => round((float) $rows->sum('overpaid'), 2),
            'unapplied_total' => round((float) $rows->sum('unapplied'), 2),
            'collection_rate' => $billed > Catalog::TOLERANCE ? round(min(1.0, $paid / $billed), 4) : null,
            'unpaid_count' => $this->countByState($rows, Catalog::STATE_UNPAID),
            'partially_paid_count' => $this->countByState($rows, Catalog::STATE_PARTIALLY_PAID),
            'paid_count' => $this->countByState($rows, Catalog::STATE_PAID),
            'overdue_count' => $rows->where('overdue', '>', Catalog::TOLERANCE)->count(),
            'overpaid_count' => $rows->where('overpaid', '>', Catalog::TOLERANCE)->count(),
            'unapplied_count' => $rows->where('has_unapplied', true)->count(),
            'lifecycle_exception_count' => $rows->where('is_lifecycle_exception', true)->count(),
        ];
    }

    /**
     * Grouped breakdowns across every dimension the lens supports.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function breakdownsFromRows(Collection $rows): array
    {
        return [
            'by_fee_type' => $this->byFeeType($rows),
            'by_program' => $this->byStudentDimension($rows, 'program_code', fn (array $row) => $row['program_code'] ?? '—'),
            'by_intake' => $this->byStudentDimension($rows, 'intake_semester_id', fn (array $row) => $row['intake_semester_id'] !== null ? 'Intake '.$row['intake_semester_id'] : '—'),
            'by_cohort' => $this->byStudentDimension($rows, 'cohort', fn (array $row) => $row['cohort'] !== null ? 'Cohort '.$row['cohort'] : '—'),
            'by_balance_state' => $this->byBalanceState($rows),
            'by_aging_bucket' => $this->byAgingBucket($rows),
            'by_lifecycle_exception' => $this->byLifecycleException($rows),
        ];
    }

    /**
     * Bridges this lens to the same `fromRows` contract used by the controller's
     * empty-state fallback.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(int $semesterId, array $filters = []): array
    {
        return $this->fromRows(app(ListCollectionProgressQuery::class)->collectRows($semesterId, $filters));
    }

    /**
     * Fee-type money split, summed from each row's per-fee-type ledger breakdown.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byFeeType(Collection $rows): array
    {
        /** @var array<string, array{billed: float, paid: float, outstanding: float, student_count: int}> $totals */
        $totals = [];

        foreach ($rows as $row) {
            foreach (($row['fee_type_breakdown'] ?? []) as $type => $amounts) {
                $totals[$type] ??= ['billed' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0, 'student_count' => 0];
                $totals[$type]['billed'] += (float) $amounts['billed'];
                $totals[$type]['paid'] += (float) $amounts['paid'];
                $totals[$type]['outstanding'] += (float) $amounts['outstanding'];
                $totals[$type]['student_count']++;
            }
        }

        return collect($totals)
            ->map(fn (array $amounts, string $type) => [
                'key' => $type,
                'label' => $type,
                'student_count' => $amounts['student_count'],
                'billed' => round($amounts['billed'], 2),
                'paid' => round($amounts['paid'], 2),
                'outstanding' => round($amounts['outstanding'], 2),
            ])
            ->sortByDesc('outstanding')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): string  $labelResolver
     * @return list<array<string, mixed>>
     */
    private function byStudentDimension(Collection $rows, string $field, callable $labelResolver): array
    {
        return $rows
            ->groupBy(fn (array $row) => (string) ($row[$field] ?? '—'))
            ->map(fn (Collection $group, string $key) => [
                'key' => $key,
                'label' => $labelResolver($group->first()),
                'student_count' => $group->count(),
                'billed' => round((float) $group->sum('billed'), 2),
                'paid' => round((float) $group->sum('paid'), 2),
                'outstanding' => round((float) $group->sum('outstanding'), 2),
            ])
            ->sortByDesc('outstanding')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byBalanceState(Collection $rows): array
    {
        return collect(Catalog::balanceStates())
            ->map(function (string $label, string $state) use ($rows) {
                $group = $rows->where('balance_state', $state);

                return [
                    'key' => $state,
                    'label' => $label,
                    'student_count' => $group->count(),
                    'outstanding' => round((float) $group->sum('outstanding'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byAgingBucket(Collection $rows): array
    {
        return collect(Catalog::agingBuckets())
            ->map(function (array $meta, string $bucket) use ($rows) {
                $group = $rows->where('aging_bucket', $bucket);

                return [
                    'key' => $bucket,
                    'label' => $meta['label'],
                    'student_count' => $group->count(),
                    'outstanding' => round((float) $group->sum('outstanding'), 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function byLifecycleException(Collection $rows): array
    {
        $flagged = $rows->where('is_lifecycle_exception', true);
        $normal = $rows->where('is_lifecycle_exception', false);

        return [
            [
                'key' => 'flagged',
                'label' => 'Ngoại lệ vòng đời (SV không ở trạng thái thu phí)',
                'student_count' => $flagged->count(),
                'outstanding' => round((float) $flagged->sum('outstanding'), 2),
            ],
            [
                'key' => 'normal',
                'label' => 'Trong phạm vi thu phí',
                'student_count' => $normal->count(),
                'outstanding' => round((float) $normal->sum('outstanding'), 2),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function countByState(Collection $rows, string $state): int
    {
        return $rows->where('balance_state', $state)->count();
    }
}
