<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use App\Modules\Finance\Support\Reporting\DngLifecycleCatalog as Catalog;
use Illuminate\Support\Collection;

/**
 * Derives the DNG/Payment Lifecycle summary tiles and grouped breakdowns
 * (FIN-REV-019) from an already-filtered row collection, so the statistics
 * always match exactly the rows the operator can see.
 */
class GetDngLifecycleSummaryQuery
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function fromRows(Collection $rows): array
    {
        $attention = $rows->where('needs_attention', true);

        return [
            'total_count' => $rows->count(),
            'needs_attention_count' => $attention->count(),
            'total_amount' => round((float) $rows->sum('amount'), 2),
            'attention_amount' => round((float) $attention->sum('amount'), 2),
            'failed_request_count' => $this->bucketCount($rows, Catalog::BUCKET_FAILED_REQUEST),
            'webhook_problem_count' => $this->bucketCount($rows, Catalog::BUCKET_WEBHOOK_PROBLEM),
            'paid_uninvoiced_count' => $this->bucketCount($rows, Catalog::BUCKET_PAID_UNINVOICED),
            'pending_stale_count' => $this->bucketCount($rows, Catalog::BUCKET_PENDING_STALE),
            'overdue_pushed_count' => $this->bucketCount($rows, Catalog::BUCKET_OVERDUE_PUSHED),
            'bridged_count' => $rows->where('payment_bridge', Catalog::BRIDGE_BRIDGED)->count(),
            'unbridged_count' => $rows->where('payment_bridge', Catalog::BRIDGE_NOT_BRIDGED)->count(),
            'outside_selected_semester_count' => $rows->where('outside_selected_semester', true)->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, list<array{key: string, label: string, count: int, amount: float}>>
     */
    public function breakdownsFromRows(Collection $rows): array
    {
        return [
            'by_attention_bucket' => $this->byAttentionBucket($rows),
            'by_status' => $this->byScalar($rows, 'status', Catalog::statuses()),
            'by_webhook_state' => $this->byScalar($rows, 'webhook_state', Catalog::webhookStates()),
            'by_payment_bridge' => $this->byScalar($rows, 'payment_bridge', Catalog::paymentBridgeStates()),
            'by_invoice_state' => $this->byScalar($rows, 'invoice_state', Catalog::invoiceStates()),
            'by_related_semester' => $this->byRelatedSemester($rows),
            'by_fee_type' => $this->byFeeType($rows),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function bucketCount(Collection $rows, string $bucket): int
    {
        return $rows->filter(fn (array $row) => in_array($bucket, $row['attention_buckets'], true))->count();
    }

    /**
     * Group rows by a scalar key, preserving the catalog's display order and
     * dropping groups with no rows.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function byScalar(Collection $rows, string $field, array $labels): array
    {
        $grouped = $rows->groupBy($field);

        $items = [];
        foreach ($labels as $key => $label) {
            $group = $grouped->get($key);
            if ($group === null || $group->isEmpty()) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => $label,
                'count' => $group->count(),
                'amount' => round((float) $group->sum('amount'), 2),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function byAttentionBucket(Collection $rows): array
    {
        $items = [];
        foreach (Catalog::attentionBuckets() as $key => $label) {
            $group = $rows->filter(fn (array $row) => in_array($key, $row['attention_buckets'], true));
            if ($group->isEmpty()) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => $label,
                'count' => $group->count(),
                'amount' => round((float) $group->sum('amount'), 2),
            ];
        }

        return $items;
    }

    /**
     * Each row contributes to every distinct related semester it touches; rows
     * with no resolvable lineage land in an `unknown` group. This is a triage
     * count, not a money report, so a multi-semester row is counted per semester.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function byRelatedSemester(Collection $rows): array
    {
        /** @var array<string, array{key: string, label: string, count: int, amount: float}> $buckets */
        $buckets = [];

        foreach ($rows as $row) {
            $related = $row['related_semesters'];
            $amount = (float) $row['amount'];

            if ($related === []) {
                $key = 'unknown';
                $buckets[$key] ??= ['key' => $key, 'label' => Catalog::semesterStateLabel(Catalog::SEMESTER_UNKNOWN), 'count' => 0, 'amount' => 0.0];
                $buckets[$key]['count']++;
                $buckets[$key]['amount'] += $amount;

                continue;
            }

            foreach ($related as $semester) {
                $key = (string) $semester['id'];
                $buckets[$key] ??= ['key' => $key, 'label' => $semester['name'], 'count' => 0, 'amount' => 0.0];
                $buckets[$key]['count']++;
                $buckets[$key]['amount'] += $amount;
            }
        }

        $items = array_values($buckets);
        usort($items, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return array_map(fn (array $item) => [
            'key' => $item['key'],
            'label' => $item['label'],
            'count' => $item['count'],
            'amount' => round($item['amount'], 2),
        ], $items);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{key: string, label: string, count: int, amount: float}>
     */
    private function byFeeType(Collection $rows): array
    {
        $items = $rows->groupBy('fee_type')
            ->map(fn (Collection $group, string $type) => [
                'key' => $type,
                'label' => $type,
                'count' => $group->count(),
                'amount' => round((float) $group->sum('amount'), 2),
            ])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();

        return $items;
    }
}
