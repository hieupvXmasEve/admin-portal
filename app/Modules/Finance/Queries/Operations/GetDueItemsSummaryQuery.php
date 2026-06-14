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
                ->when($semesterId, fn ($q) => $q->where('dng_payment_requests.semester_id', $semesterId))
                ->where('dng_payment_requests.status', 'pushed_to_dng')
                ->whereNotNull('dng_payment_requests.due_date');

            LifecycleDueItemPredicate::applyActiveCollectionScope($query, $campusId);

            return $query;
        };

        $dngSummary = [
            'upcoming_count' => $dngBaseQuery()
                ->whereBetween('dng_payment_requests.due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)])
                ->count(),
            'due_today_count' => $dngBaseQuery()
                ->whereDate('dng_payment_requests.due_date', $today)
                ->count(),
            'overdue_count' => $dngBaseQuery()
                ->where('dng_payment_requests.due_date', '<', $today)
                ->count(),
            'total_overdue_amount' => (float) $dngBaseQuery()
                ->where('dng_payment_requests.due_date', '<', $today)
                ->sum('dng_payment_requests.amount'),
        ];

        // Only DNG requests - no invoices
        return $dngSummary;
    }
}
