<?php

declare(strict_types=1);

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Actions\HandleOutboxEventAction;
use App\Modules\Notification\Enums\NotificationOutboxStatus;
use App\Modules\Notification\Models\NotificationEventOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchSingleOutboxEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $outboxId) {}

    public function handle(HandleOutboxEventAction $handleOutboxEventAction): void
    {
        $claimed = NotificationEventOutbox::query()
            ->where('id', $this->outboxId)
            ->where('status', NotificationOutboxStatus::Pending)
            ->update([
                'status' => NotificationOutboxStatus::Processing,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            // Already claimed by the cron safety-net or another worker — skip.
            return;
        }

        $outbox = NotificationEventOutbox::find($this->outboxId);
        if ($outbox === null) {
            return;
        }

        $handleOutboxEventAction->run($outbox);
    }

    public function failed(Throwable $exception): void
    {
        // Reset to Pending so the cron safety-net retries with backoff.
        NotificationEventOutbox::query()
            ->where('id', $this->outboxId)
            ->where('status', NotificationOutboxStatus::Processing)
            ->update([
                'status' => NotificationOutboxStatus::Pending,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                'next_retry_at' => now()->addSeconds(10),
                'updated_at' => now(),
            ]);
    }
}
