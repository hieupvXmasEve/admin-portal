<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\DeferCase;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistPresenter;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistReader;
use Illuminate\Database\Eloquent\Builder;

final class GetBillingDashboardStatsQuery
{
    public function __construct(
        private readonly SettlementPositionWorklistReader $positionReader,
        private readonly SettlementPositionWorklistPresenter $positionPresenter,
    ) {}

    /** @return array<string,int|float> */
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
                'needs_review_count' => 0,
                'total_receivable' => 0,
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
            ->where(function (Builder $q) use ($semesterId): void {
                $q->whereHas('courseRegistrations', fn ($sq) => $sq->where('semester_id', $semesterId)->whereNotIn('registration_status', ['defer', 'dropped', 'withdrawn']))
                    ->orWhereHas('deferCases', fn ($sq) => $sq->where('semester_id', $semesterId))
                    ->orWhereHas('invoices', fn ($sq) => $sq->where('semester_id', $semesterId));
            });

        $eligibleCount = $eligibleQuery->count();
        $lines = InvoiceLine::query()
            ->with('invoice')
            ->where('status', 'active')
            ->whereHas('charge', fn ($query) => $query->where('status', FinanceCharge::STATUS_ACTIVE)->where('amount', '>', 0))
            ->whereHas('invoice', function ($query) use ($semesterId, $campusId): void {
                $query->where('semester_id', $semesterId)
                    ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)));
            })
            ->get();

        $lineIdsByStudent = $lines->groupBy(fn (InvoiceLine $line): int => (int) $line->invoice->student_id)
            ->map(fn ($studentLines): array => $studentLines->pluck('id')->map(fn ($id): int => (int) $id)->all())
            ->all();
        $positions = $this->positionReader->forLineGroups($lineIdsByStudent);

        $totals = [
            'paid_count' => 0,
            'unpaid_count' => 0,
            'partial_paid_count' => 0,
            'needs_review_count' => 0,
            'total_charges' => 0.0,
            'total_credits' => 0.0,
            'total_paid' => 0.0,
            'total_balance' => 0.0,
            'total_receivable' => 0.0,
        ];

        foreach ($positions as $position) {
            $summary = $this->positionPresenter->summarize($position);

            if (! $summary['valid']) {
                $totals['needs_review_count']++;

                continue;
            }

            $totals['total_charges'] += $summary['gross'];
            $totals['total_credits'] += $summary['discount'];
            $totals['total_receivable'] += $summary['net'];
            $totals['total_paid'] += $summary['cash'];
            $totals['total_balance'] += $summary['remaining'];

            if ($summary['remaining'] <= 0) {
                $totals['paid_count']++;
            } elseif ($summary['cash'] > 0 || $summary['credit'] > 0 || $summary['discount'] > 0) {
                $totals['partial_paid_count']++;
            } else {
                $totals['unpaid_count']++;
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
            ->where(function ($query) use ($semesterId): void {
                $query->whereDoesntHave('invoices', fn ($invoiceQuery) => $invoiceQuery->where('semester_id', $semesterId))
                    ->orWhereHas('invoices', fn ($invoiceQuery) => $invoiceQuery->where('semester_id', $semesterId)->whereNotIn('status', ['paid', 'cancelled']));
            })
            ->count();

        return [
            'eligible_count' => $eligibleCount,
            'charged_count' => count($lineIdsByStudent),
            'uncharged_count' => max(0, $eligibleCount - count($lineIdsByStudent)),
            ...$totals,
            'defer_preserve_count' => $deferPreserveCount,
            'defer_forfeit_count' => $deferForfeitCount,
            'retake_unpaid_count' => $retakeUnpaidCount,
        ];
    }
}
