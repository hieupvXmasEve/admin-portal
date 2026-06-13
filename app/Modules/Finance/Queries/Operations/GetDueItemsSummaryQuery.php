<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;

class GetDueItemsSummaryQuery
{
    public function handle(?int $semesterId): array
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $today = now()->startOfDay();

        // Only DNG requests - no need to check for student exclusion

        // DNG Requests summary
        $dngBaseQuery = function () use ($semesterId, $campusId) {
            $query = DngPaymentRequest::query()
                ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
                ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
                ->where('status', 'pushed_to_dng')
                ->whereNotNull('due_date');

            LifecycleDueItemPredicate::applyActiveCollectionScope($query);

            return $query;
        };

        $dngSummary = [
            'upcoming_count' => $dngBaseQuery()
                ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
                ->count(),
            'due_today_count' => $dngBaseQuery()
                ->whereDate('due_date', $today)
                ->count(),
            'overdue_count' => $dngBaseQuery()
                ->where('due_date', '<', $today)
                ->count(),
            'total_overdue_amount' => (float) $dngBaseQuery()
                ->where('due_date', '<', $today)
                ->sum('amount'),
        ];

        // Only DNG requests - no invoices
        return $dngSummary;
    }
}
