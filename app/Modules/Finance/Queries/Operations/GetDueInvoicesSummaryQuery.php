<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\StudentInvoice;

class GetDueInvoicesSummaryQuery
{
    public function handle(?int $semesterId): array
    {
        $campusId = app('campus')?->id;
        $today = now()->startOfDay();

        $baseQuery = fn () => StudentInvoice::query()
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)));

        return [
            'upcoming_count' => $baseQuery()
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $today->copy()->addDays(7)])
                ->count(),
            'due_today_count' => $baseQuery()
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', $today)
                ->count(),
            'overdue_count' => $baseQuery()
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', $today)
                ->count(),
            'total_overdue_amount' => (float) $baseQuery()
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->whereNotNull('due_date')
                ->where('due_date', '<', $today)
                ->get()
                ->sum(fn ($invoice) => $invoice->outstanding_balance),
        ];
    }
}
