<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\DeferCase;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\FinanceOperationsStudentScope;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistPresenter;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionWorklistReader;

final class GetBillingDashboardStatsQuery
{
    public function __construct(
        private readonly SettlementPositionWorklistReader $positionReader,
        private readonly SettlementPositionWorklistPresenter $positionPresenter,
        private readonly FinanceOperationsStudentScope $studentScope,
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

        $eligibleStudentIds = array_keys($this->studentScope->dashboardStudents($semesterId, $campusId === null ? null : (int) $campusId));
        $eligibleCount = count($eligibleStudentIds);
        $lines = InvoiceLine::query()
            ->with('invoice')
            ->where('status', 'active')
            ->whereHas('charge', fn ($query) => $query->where('status', FinanceCharge::STATUS_ACTIVE)->where('amount', '>', 0))
            ->whereHas('invoice', fn ($query) => $query->where('semester_id', $semesterId)->whereIn('student_id', $eligibleStudentIds))
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

        $deferCases = $this->studentScope->deferCases($semesterId, $campusId === null ? null : (int) $campusId);
        $deferPreserveCount = count(array_filter($deferCases, static fn (array $deferCase): bool => $deferCase['fee_policy'] === DeferCase::POLICY_PRESERVE));
        $deferForfeitCount = count(array_filter($deferCases, static fn (array $deferCase): bool => $deferCase['fee_policy'] === DeferCase::POLICY_FORFEIT));
        $retakeStudentIds = $this->studentScope->retakeStudentIds($semesterId, $campusId === null ? null : (int) $campusId);
        $retakeStudentIdsWithInvoices = StudentInvoice::query()
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', $retakeStudentIds)
            ->pluck('student_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $unsettledRetakeStudentIds = StudentInvoice::query()
            ->where('semester_id', $semesterId)
            ->whereIn('student_id', $retakeStudentIds)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->pluck('student_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $retakeUnpaidCount = count(array_unique([
            ...array_diff($retakeStudentIds, $retakeStudentIdsWithInvoices),
            ...$unsettledRetakeStudentIds,
        ]));

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
