<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Cockpit;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStatsQuery;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\GetDueItemsSummaryQuery;
use App\Modules\Finance\Queries\Operations\GetInstallmentPushFailureCountQuery;
use App\Modules\Finance\Queries\Operations\GetLifecycleDueExceptionSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use App\Modules\Finance\Support\FinanceCollectionPhase;
use Illuminate\Http\Request;

/**
 * Light cockpit overview: KPI ribbon + the six "Cần xử lý" queue counts + phase.
 * Reuses existing campus-scoped summary queries; computes no money. Semester-bound
 * queues honor $semesterId; campus-wide ones (webhook/unallocated) ignore it so an
 * urgent item is never hidden by a semester change (§3.1).
 */
class GetFinanceCockpitOverviewQuery
{
    public function __construct(
        private GetBillingDashboardStatsQuery $dashboardStats,
        private GetDueItemsSummaryQuery $dueSummary,
        private GetLifecycleDueExceptionSummaryQuery $lifecycleSummary,
        private GetBillingExceptionCountsQuery $exceptionCounts,
        private GetInstallmentPushFailureCountQuery $installmentFailures,
        private ListSettlementWorklistQuery $settlementWorklist,
    ) {}

    /** @return array<string,mixed> */
    public function handle(?int $semesterId, ?string $phaseOverride = null): array
    {
        return [
            'kpi' => $this->kpi($semesterId),
            'queues' => $this->queues($semesterId),
            'phase' => FinanceCollectionPhase::infer($phaseOverride),
        ];
    }

    /** @return array<string,mixed> */
    private function kpi(?int $semesterId): array
    {
        $s = $this->dashboardStats->handle($semesterId);
        $receivable = (float) $s['total_charges'] - (float) $s['total_credits'];
        $collectedPct = $receivable > 0 ? round(((float) $s['total_paid'] / $receivable) * 100, 1) : 0.0;

        return [
            'total_receivable' => $receivable,
            'total_collected' => (float) $s['total_paid'],
            'collected_pct' => $collectedPct,
            'uncharged_count' => (int) $s['uncharged_count'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function queues(?int $semesterId): array
    {
        $webhook = DngWebhookEvent::query()
            ->whereIn('processing_status', [
                DngWebhookEvent::STATUS_FAILED_RETRYABLE,
                DngWebhookEvent::STATUS_FAILED_TERMINAL,
                DngWebhookEvent::STATUS_MISMATCH,
                DngWebhookEvent::STATUS_SKIPPED,
            ]);
        $webhookCount = (clone $webhook)->count();

        $settlement = $this->settlementWorklist->handle(Request::create(
            route('finance.operations.settlement.index'),
            'GET',
            ['readiness' => 'ready', 'per_page' => 1],
        ));
        $readySettlementCount = (int) $settlement['summary']['ready_students'];

        $due = $this->dueSummary->handle($semesterId);
        $lifecycle = $this->lifecycleSummary->handle($semesterId);
        $exceptions = $this->exceptionCounts->handle($semesterId);
        $installments = $this->installmentFailures->handle(null);

        return [
            $this->queue('webhook_errors', 'Webhook DNG lỗi', $webhookCount, false, 'campus',
                'view_finance_dng_webhook_events', route('finance.dng.webhook-events.index'), $webhookCount > 0 ? 'critical' : 'normal'),
            $this->queue('unallocated', 'Tiền chờ phân bổ', $readySettlementCount, false, 'campus',
                'allocate_finance_payment', route('finance.operations.settlement.index', ['readiness' => 'ready']), $readySettlementCount > 0 ? 'action' : 'normal'),
            $this->queue('dng_due', 'DNG đến hạn', (int) $due['overdue_count'] + (int) $due['due_today_count'], true, 'semester',
                'view_finance_operations_due_calendar', route('finance.operations.due-calendar'), (int) $due['overdue_count'] > 0 ? 'action' : 'normal'),
            $this->queue('lifecycle', 'Ngoại lệ lifecycle chờ review', (int) $lifecycle['total_count'], true, 'semester',
                'view_finance_operations_due_calendar', route('finance.operations.lifecycle-exceptions'), 'action'),
            $this->queue('charge_errors', 'Sai sót sinh phí', (int) array_sum($exceptions), true, 'semester',
                'view_finance_operations_exceptions', route('finance.operations.exceptions'), 'action'),
            $this->queue('installment_failures', 'Installment đẩy thất bại', (int) $installments['count'], false, 'campus',
                'create_finance_payments', route('finance.batch-studio.dng'), (int) $installments['count'] > 0 ? 'action' : 'normal'),
        ];
    }

    /** @return array<string,mixed> */
    private function queue(string $key, string $label, int $count, bool $obeysSemester, string $scopeBadge, string $permission, string $actionUrl, string $severity): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'obeys_semester' => $obeysSemester,
            'scope_badge' => $scopeBadge,
            'permission' => $permission,
            'action_url' => $actionUrl,
            'severity' => $severity,
        ];
    }
}
