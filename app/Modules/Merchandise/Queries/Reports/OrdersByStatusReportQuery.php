<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Queries\Reports;

use App\Models\RedemptionOrder;
use Illuminate\Support\Facades\DB;

/**
 * Redemption order counts per status + the four operational queue lists
 * (pending review, ready for collection, pickup overdue, shipped). Read-only
 * — campus scope is the caller's responsibility via $campusIds (RT-13,
 * resolved from granted campuses, not session).
 */
class OrdersByStatusReportQuery
{
    /** Rows returned per queue list — the UI is a worklist, not a full table. */
    private const QUEUE_LIMIT = 50;

    /** @var list<string> Statuses that get a worklist, not just a count. */
    private const QUEUE_STATUSES = [
        RedemptionOrder::STATUS_PENDING_REVIEW,
        RedemptionOrder::STATUS_READY_FOR_COLLECTION,
        RedemptionOrder::STATUS_PICKUP_OVERDUE,
        RedemptionOrder::STATUS_SHIPPED,
    ];

    /**
     * @param  list<int>  $campusIds
     * @return array{counts: array<string, int>, queues: array<string, list<array<string, mixed>>>}
     */
    public function handle(array $campusIds, ?string $dateFrom, ?string $dateTo, ?string $onlyStatus = null): array
    {
        if ($campusIds === []) {
            return ['counts' => $this->emptyCounts(), 'queues' => $this->emptyQueues()];
        }

        $baseQuery = fn () => RedemptionOrder::query()
            ->whereIn('campus_id', $campusIds)
            ->when($dateFrom, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($dateTo, fn ($query, $to) => $query->where('created_at', '<=', $to));

        $counts = $this->emptyCounts();
        $baseQuery()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->each(function ($count, $status) use (&$counts) {
                $counts[$status] = (int) $count;
            });

        $queueStatuses = $onlyStatus !== null ? array_intersect(self::QUEUE_STATUSES, [$onlyStatus]) : self::QUEUE_STATUSES;

        $queues = [];
        foreach ($queueStatuses as $status) {
            $queues[$status] = $baseQuery()
                ->where('status', $status)
                ->with('student:id,full_name')
                ->orderBy('created_at')
                ->limit(self::QUEUE_LIMIT)
                ->get(['id', 'code', 'campus_id', 'student_id', 'status', 'total_gold', 'created_at', 'ready_at', 'collection_deadline', 'shipped_at'])
                ->toArray();
        }

        return ['counts' => $counts, 'queues' => $queues];
    }

    /** @return array<string, int> */
    private function emptyCounts(): array
    {
        return array_fill_keys(RedemptionOrder::STATUSES, 0);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function emptyQueues(): array
    {
        return array_fill_keys(self::QUEUE_STATUSES, []);
    }
}
