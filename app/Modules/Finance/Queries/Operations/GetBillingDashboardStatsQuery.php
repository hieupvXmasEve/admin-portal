<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\DeferCase;
use App\Models\Student;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Database\Eloquent\Builder;

class GetBillingDashboardStatsQuery
{
    public function __construct(
        protected SettlementService $settlementService,
    ) {}

    public function handle(?int $semesterId): array
    {
        $campusId = app()->bound('campus') ? app('campus')->id : null;

        if (! $semesterId) {
            return [
                'eligible_count' => 0,
                'charged_count' => 0,
                'uncharged_count' => 0,
                'paid_count' => 0,
                'unpaid_count' => 0,
                'partial_paid_count' => 0,
                'total_charges' => 0,
                'total_credits' => 0,
                'total_paid' => 0,
                'total_balance' => 0,
                'defer_preserve_count' => 0,
                'defer_forfeit_count' => 0,
                'retake_unpaid_count' => 0,
            ];
        }

        $eligibleQuery = Student::query()
            ->where('intake_semester_id', '<=', $semesterId)
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->where(function (Builder $q) use ($semesterId) {
                $q->whereHas('courseRegistrations', fn ($sq) => $sq->where('semester_id', $semesterId)->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn']));
                $q->orWhereHas('deferCases', fn ($sq) => $sq->where('semester_id', $semesterId));
                $q->orWhereHas('invoices', fn ($sq) => $sq->where('semester_id', $semesterId));
            });

        $eligibleCount = $eligibleQuery->count();

        $semesterInvoices = StudentInvoice::query()
            ->with(['invoiceLines.charge', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations'])
            ->where('semester_id', $semesterId)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->get();

        $chargedCount = $semesterInvoices->pluck('student_id')->unique()->count();

        $snapshots = $semesterInvoices->mapWithKeys(fn (StudentInvoice $invoice) => [
            $invoice->id => $this->settlementService->deriveInvoiceSnapshot($invoice),
        ]);

        $totalCharges = (float) $semesterInvoices->sum(fn (StudentInvoice $invoice) => $snapshots[$invoice->id]['gross']);
        $totalCredits = (float) $semesterInvoices->sum(fn (StudentInvoice $invoice) => $snapshots[$invoice->id]['discount']);
        $totalPaid = (float) $semesterInvoices->sum(fn (StudentInvoice $invoice) => $snapshots[$invoice->id]['paid']);
        $totalBalance = (float) $semesterInvoices->sum(fn (StudentInvoice $invoice) => $snapshots[$invoice->id]['remaining']);

        $invoiceBalances = $semesterInvoices
            ->groupBy('student_id')
            ->map(function ($invoices) use ($snapshots) {
                return [
                    'net_due' => (float) $invoices->sum(fn (StudentInvoice $invoice) => $snapshots[$invoice->id]['net']),
                    'paid' => (float) $invoices->sum(fn (StudentInvoice $invoice) => $snapshots[$invoice->id]['paid']),
                ];
            });

        $paidCount = 0;
        $partialCount = 0;
        $unpaidCount = 0;

        foreach ($invoiceBalances as $balance) {
            $netDue = (float) $balance['net_due'];
            $paid = (float) $balance['paid'];
            $remaining = max(0, $netDue - $paid);

            if ($netDue <= 0 || $remaining <= 0) {
                $paidCount++;
            } elseif ($paid > 0) {
                $partialCount++;
            } else {
                $unpaidCount++;
            }
        }

        $deferPreserveCount = DeferCase::query()
            ->where('semester_id', $semesterId)
            ->where('fee_policy', DeferCase::POLICY_PRESERVE)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->count();

        $deferForfeitCount = DeferCase::query()
            ->where('semester_id', $semesterId)
            ->where('fee_policy', DeferCase::POLICY_FORFEIT)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->count();

        $retakeUnpaidCount = Student::query()
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->whereHas('courseRegistrations', fn ($q) => $q->where('semester_id', $semesterId)->where('is_retake', true))
            ->where(function ($query) use ($semesterId) {
                // NT4/FIN-28: do not compare raw cache amounts as truth. Filter on
                // the recalc-maintained lifecycle status, excluding both paid and
                // cancelled exactly like the settlement worklist
                // (ListSettlementWorklistQuery: whereNotIn(['paid','cancelled'])).
                // A fully ledger-derived count belongs to the dashboard slice (FIN-28).
                $query->whereDoesntHave('invoices', fn ($invoiceQuery) => $invoiceQuery->where('semester_id', $semesterId))
                    ->orWhereHas('invoices', fn ($invoiceQuery) => $invoiceQuery->where('semester_id', $semesterId)->whereNotIn('status', ['paid', 'cancelled']));
            })
            ->count();

        return [
            'eligible_count' => $eligibleCount,
            'charged_count' => $chargedCount,
            'uncharged_count' => max(0, $eligibleCount - $chargedCount),
            'paid_count' => $paidCount,
            'unpaid_count' => $unpaidCount,
            'partial_paid_count' => $partialCount,
            'total_charges' => $totalCharges,
            'total_credits' => $totalCredits,
            'total_paid' => $totalPaid,
            'total_balance' => $totalBalance,
            'defer_preserve_count' => $deferPreserveCount,
            'defer_forfeit_count' => $deferForfeitCount,
            'retake_unpaid_count' => $retakeUnpaidCount,
        ];
    }
}
