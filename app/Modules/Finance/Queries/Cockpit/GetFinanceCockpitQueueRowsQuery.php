<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Cockpit;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Models\FinanceChargeInstallment;

/**
 * Top-N rows for a single cockpit queue, fetched on demand when the Action Panel
 * opens. Bounded by $limit; reuses existing models/queries. Read-only.
 */
class GetFinanceCockpitQueueRowsQuery
{
    private const LIMIT = 15;

    /** @return list<array<string,mixed>> */
    public function handle(string $queueKey, ?int $semesterId): array
    {
        return match ($queueKey) {
            'webhook_errors' => $this->webhookRows(),
            'installment_failures' => $this->installmentRows(),
            default => [],
        };
    }

    /** @return list<array<string,mixed>> */
    private function webhookRows(): array
    {
        return DngWebhookEvent::query()
            ->whereIn('processing_status', [
                DngWebhookEvent::STATUS_FAILED_RETRYABLE,
                DngWebhookEvent::STATUS_FAILED_TERMINAL,
                DngWebhookEvent::STATUS_MISMATCH,
                DngWebhookEvent::STATUS_SKIPPED,
            ])
            ->orderBy('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (DngWebhookEvent $e) => [
                'id' => (int) $e->id,
                'title' => 'Webhook #'.$e->id.' · '.$e->processing_status,
                'subtitle' => (string) ($e->error_message ?? ''),
                'age' => $e->created_at?->diffForHumans(),
                'student_id' => null,
                'primary_action' => [
                    'kind' => 'retry_webhook',
                    'url' => route('finance.dng.webhook-events.retry', ['dngWebhookEvent' => $e->id]),
                    'label' => 'Retry',
                ],
            ])->all();
    }

    /** @return list<array<string,mixed>> */
    private function installmentRows(): array
    {
        return FinanceChargeInstallment::query()
            ->where('status', FinanceChargeInstallment::STATUS_PENDING)
            ->whereNotNull('last_push_error')
            ->orderBy('last_push_attempted_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (FinanceChargeInstallment $i) => [
                'id' => (int) $i->id,
                'title' => 'Kỳ '.$i->installment_no.' · charge #'.$i->finance_charge_id,
                'subtitle' => (string) ($i->last_push_error ?? ''),
                'age' => $i->last_push_attempted_at?->diffForHumans(),
                'student_id' => null,
                'primary_action' => [
                    'kind' => 'retry_installment',
                    'url' => route('finance.charges.installments.retry-push', ['charge' => $i->finance_charge_id, 'installment' => $i->id]),
                    'label' => 'Đẩy lại',
                ],
            ])->all();
    }
}
