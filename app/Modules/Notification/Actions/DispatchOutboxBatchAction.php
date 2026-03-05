<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Models\NotificationEventOutbox;
use Illuminate\Support\Facades\DB;
use Throwable;

class DispatchOutboxBatchAction
{
    public function __construct(
        private readonly HandleOutboxEventAction $handleOutboxEventAction,
    ) {}

    /**
     * @return array{processed:int,queued_deliveries:int,failed:int}
     */
    public function run(int $limit = 100): array
    {
        if (! (bool) config('notification.v2_enabled', false)) {
            return ['processed' => 0, 'queued_deliveries' => 0, 'failed' => 0];
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2_only', 'v2'], true)) {
            return ['processed' => 0, 'queued_deliveries' => 0, 'failed' => 0];
        }

        $processed = 0;
        $failed = 0;
        $queuedDeliveries = 0;

        $items = DB::transaction(function () use ($limit) {
            $candidates = NotificationEventOutbox::query()
                ->where('status', NotificationOutboxStatus::Pending->value)
                ->where(function ($query) {
                    $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $claimed = collect();

            foreach ($candidates as $candidate) {
                $updated = NotificationEventOutbox::query()
                    ->where('id', $candidate->id)
                    ->where('status', NotificationOutboxStatus::Pending->value)
                    ->update([
                        'status' => NotificationOutboxStatus::Processing->value,
                        'updated_at' => now(),
                    ]);

                if ($updated === 1) {
                    $candidate->status = NotificationOutboxStatus::Processing;
                    $claimed->push($candidate);
                }
            }

            return $claimed;
        });

        foreach ($items as $item) {
            try {
                $queuedDeliveries += $this->handleOutboxEventAction->run($item);
                $processed++;
            } catch (Throwable $exception) {
                $this->markFailure($item, $exception);
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'queued_deliveries' => $queuedDeliveries,
            'failed' => $failed,
        ];
    }

    private function markFailure(NotificationEventOutbox $item, Throwable $exception): void
    {
        $maxAttempts = (int) config('notification.outbox.max_attempts', 8);
        $attempts = (int) $item->attempts + 1;
        $isDead = $attempts >= $maxAttempts;

        $item->forceFill([
            'attempts' => $attempts,
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            'status' => $isDead ? NotificationOutboxStatus::Failed : NotificationOutboxStatus::Pending,
            'next_retry_at' => $isDead ? null : now()->addSeconds($this->retryAfterSeconds($attempts)),
        ])->save();
    }

    private function retryAfterSeconds(int $attempt): int
    {
        $policy = (array) config('notification.outbox.retry_seconds', [10, 30, 60, 300, 900]);
        $index = max(0, min(count($policy) - 1, $attempt - 1));

        return (int) $policy[$index];
    }
}
