<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;

class GetDueInvoicesSummaryQuery
{
    public function __construct(
        protected SettlementService $settlementService,
    ) {}

    public function handle(?int $semesterId): array
    {
        $campusId = app('campus')?->id;
        $today = now()->startOfDay();

        $baseQuery = fn () => StudentInvoice::query()
            ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)));

        $overdueInvoices = $baseQuery()
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->with([
                'invoiceLines.charge',
                'invoiceLines.paymentApplications',
                'invoiceLines.discountAllocations.invoiceDiscount',
            ])
            ->get();

        return [
            'upcoming_count' => $baseQuery()
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)])
                ->count(),
            'due_today_count' => $baseQuery()
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', $today)
                ->count(),
            'overdue_count' => $overdueInvoices->count(),
            'total_overdue_amount' => (float) $overdueInvoices->sum(
                fn (StudentInvoice $invoice) => $this->settlementService->deriveInvoiceSnapshot($invoice)['remaining']
            ),
        ];
    }
}
