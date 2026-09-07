<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Cockpit;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStatsQuery;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\GetDueItemsSummaryQuery;
use App\Modules\Finance\Queries\Operations\GetInstallmentPushFailureCountQuery;
use App\Modules\Finance\Queries\Operations\GetLifecycleDueExceptionSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use App\Modules\Finance\Queries\Operations\ListUnresolvedSurplusQuery;
use App\Modules\Finance\Support\FinanceCollectionPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Light cockpit overview: KPI ribbon + "Cần xử lý" queue counts + phase.
 * Reuses existing campus-scoped summary queries; computes no money. Semester-bound
 * queues honor $semesterId; campus-wide ones (webhook/unallocated) ignore it so an
 * urgent item is never hidden by a semester change (§3.1).
 *
 * Permission is gated here: a queue is omitted when the user cannot($permission).
 * Route middleware only checks view_finance_cockpit.
 */
class GetFinanceCockpitOverviewQuery
{
    /** @var array<string, string> */
    public const QUEUE_PERMISSIONS = [
        'webhook_errors' => 'view_finance_dng_webhook_events',
        'settlement_exceptions' => 'view_finance_operations_exceptions',
        'unallocated' => 'allocate_finance_payment',
        'dng_due' => 'view_finance_operations_due_calendar',
        'lifecycle' => 'view_finance_operations_due_calendar',
        'charge_errors' => 'view_finance_operations_exceptions',
        'installment_failures' => 'create_finance_payments',
        'cancellations' => 'create_finance_payments',
        'unresolved_surplus' => 'view_finance_student_overview',
    ];

    public function __construct(
        private GetBillingDashboardStatsQuery $dashboardStats,
        private GetDueItemsSummaryQuery $dueSummary,
        private GetLifecycleDueExceptionSummaryQuery $lifecycleSummary,
        private GetBillingExceptionCountsQuery $exceptionCounts,
        private GetInstallmentPushFailureCountQuery $installmentFailures,
        private ListSettlementWorklistQuery $settlementWorklist,
        private ListUnresolvedSurplusQuery $unresolvedSurplus,
    ) {}

    /** @return array<string,mixed> */
    public function handle(?int $semesterId, ?string $phaseOverride = null): array
    {
        $stats = $this->dashboardStats->handle($semesterId);

        return [
            'kpi' => $this->kpi($stats),
            'queues' => $this->queues($semesterId, $stats),
            'phase' => FinanceCollectionPhase::infer($phaseOverride),
        ];
    }

    /** @return array<string,mixed> */
    private function kpi(array $s): array
    {
        $receivable = (float) $s['total_receivable'];
        $collectedPct = $receivable > 0 ? round(((float) $s['total_paid'] / $receivable) * 100, 1) : 0.0;

        return [
            'total_receivable' => $receivable,
            'total_collected' => (float) $s['total_paid'],
            'collected_pct' => $collectedPct,
            'uncharged_count' => (int) $s['uncharged_count'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function queues(?int $semesterId, array $stats): array
    {
        $user = Auth::user();
        $settlement = $this->settlementWorklist->handle(Request::create(
            route('finance.operations.settlement.index'),
            'GET',
            ['readiness' => 'ready', 'per_page' => 1],
        ));
        $readySettlementCount = (int) $settlement['summary']['ready_students'];
        $out = [];

        foreach ($this->queueSpecs($semesterId, $stats, $readySettlementCount) as $spec) {
            if ($user === null || $user->cannot($spec['permission'])) {
                continue;
            }

            $count = ($spec['count'])();
            $out[] = $this->queue(
                $spec['key'],
                $spec['label'],
                $count,
                $spec['obeys_semester'],
                $spec['scope_badge'],
                $spec['permission'],
                $spec['action_url'],
                ($spec['severity'])($count),
            );
        }

        return $out;
    }

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   permission: string,
     *   obeys_semester: bool,
     *   scope_badge: string,
     *   action_url: string,
     *   count: callable(): int,
     *   severity: callable(int): string
     * }>
     */
    private function queueSpecs(?int $semesterId, array $stats, int $readySettlementCount): array
    {
        return [
            [
                'key' => 'webhook_errors',
                'label' => 'Webhook DNG lỗi',
                'permission' => self::QUEUE_PERMISSIONS['webhook_errors'],
                'obeys_semester' => false,
                'scope_badge' => 'campus',
                'action_url' => route('finance.dng.webhook-events.index'),
                'count' => fn (): int => DngWebhookEvent::query()
                    ->whereIn('processing_status', [
                        DngWebhookEvent::STATUS_FAILED_RETRYABLE,
                        DngWebhookEvent::STATUS_FAILED_TERMINAL,
                        DngWebhookEvent::STATUS_MISMATCH,
                        DngWebhookEvent::STATUS_SKIPPED,
                    ])
                    ->count(),
                'severity' => fn (int $count): string => $count > 0 ? 'critical' : 'normal',
            ],
            [
                'key' => 'settlement_exceptions',
                'label' => 'Settlement cần kiểm tra',
                'permission' => self::QUEUE_PERMISSIONS['settlement_exceptions'],
                'obeys_semester' => true,
                'scope_badge' => 'semester',
                'action_url' => route('finance.operations.exceptions'),
                'count' => fn (): int => (int) $stats['needs_review_count'],
                'severity' => fn (int $count): string => $count > 0 ? 'critical' : 'normal',
            ],
            [
                'key' => 'unallocated',
                'label' => 'Tiền chờ phân bổ',
                'permission' => self::QUEUE_PERMISSIONS['unallocated'],
                'obeys_semester' => false,
                'scope_badge' => 'campus',
                'action_url' => route('finance.operations.settlement.index', ['readiness' => 'ready']),
                'count' => fn (): int => $readySettlementCount,
                'severity' => fn (int $count): string => $count > 0 ? 'action' : 'normal',
            ],
            [
                'key' => 'dng_due',
                'label' => 'DNG đến hạn',
                'permission' => self::QUEUE_PERMISSIONS['dng_due'],
                'obeys_semester' => true,
                'scope_badge' => 'semester',
                'action_url' => route('finance.operations.due-calendar'),
                'count' => function () use ($semesterId): int {
                    $due = $this->dueSummary->handle($semesterId);

                    return (int) $due['overdue_count'] + (int) $due['due_today_count'];
                },
                'severity' => fn (int $count): string => $count > 0 ? 'action' : 'normal',
            ],
            [
                'key' => 'lifecycle',
                'label' => 'Ngoại lệ lifecycle chờ review',
                'permission' => self::QUEUE_PERMISSIONS['lifecycle'],
                'obeys_semester' => true,
                'scope_badge' => 'semester',
                'action_url' => route('finance.operations.lifecycle-exceptions'),
                'count' => fn (): int => (int) $this->lifecycleSummary->handle($semesterId)['total_count'],
                'severity' => fn (int $count): string => $count > 0 ? 'action' : 'normal',
            ],
            [
                'key' => 'charge_errors',
                'label' => 'Sai sót sinh phí',
                'permission' => self::QUEUE_PERMISSIONS['charge_errors'],
                'obeys_semester' => true,
                'scope_badge' => 'semester',
                'action_url' => route('finance.operations.exceptions'),
                'count' => fn (): int => (int) array_sum($this->exceptionCounts->handle($semesterId)),
                'severity' => fn (int $count): string => $count > 0 ? 'action' : 'normal',
            ],
            [
                'key' => 'installment_failures',
                'label' => 'Installment đẩy thất bại',
                'permission' => self::QUEUE_PERMISSIONS['installment_failures'],
                'obeys_semester' => false,
                'scope_badge' => 'campus',
                'action_url' => route('finance.batch-studio.dng'),
                'count' => fn (): int => (int) $this->installmentFailures->handle(null)['count'],
                'severity' => fn (int $count): string => $count > 0 ? 'action' : 'normal',
            ],
            [
                'key' => 'cancellations',
                'label' => 'Huỷ đang chờ',
                'permission' => self::QUEUE_PERMISSIONS['cancellations'],
                'obeys_semester' => false,
                'scope_badge' => 'campus',
                'action_url' => route('finance.search'),
                'count' => fn (): int => FinanceCancellationOperation::query()
                    ->where('status', FinanceCancellationOperation::STATUS_REQUIRES_REVIEW)
                    ->count(),
                'severity' => fn (int $count): string => $count > 0 ? 'action' : 'normal',
            ],
            [
                'key' => 'unresolved_surplus',
                'label' => 'Số dư cần quyết',
                'permission' => self::QUEUE_PERMISSIONS['unresolved_surplus'],
                'obeys_semester' => false,
                'scope_badge' => 'campus',
                'action_url' => route('finance.search'),
                'count' => fn (): int => count($this->surplusRows()),
                'severity' => fn (int $count): string => $count > 0 ? 'action' : 'normal',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function surplusRows(): array
    {
        $campusId = app()->bound('campus') ? app('campus')?->id : null;
        $canViewAll = Auth::user()?->can('view_finance_all_campus') ?? false;

        if ($campusId === null && ! $canViewAll) {
            return [];
        }

        return $this->unresolvedSurplus->handle(
            $campusId === null ? null : (int) $campusId,
            $canViewAll,
        );
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
