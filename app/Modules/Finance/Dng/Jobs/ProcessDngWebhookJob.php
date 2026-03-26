<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Jobs;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Dng\Services\DngWebhookService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDngWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public function __construct(
        protected int $eventId,
    ) {
        $this->onQueue('webhooks');
    }

    public function uniqueId(): string
    {
        return 'dng-webhook-'.$this->eventId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300, 600];
    }

    public function handle(DngWebhookService $webhookService): void
    {
        $event = DngWebhookEvent::find($this->eventId);

        if (! $event) {
            Log::warning('DNG webhook job: event not found', ['event_id' => $this->eventId]);

            return;
        }

        // Skip if already processed
        if ($event->processing_status === DngWebhookEvent::STATUS_PROCESSED) {
            return;
        }

        $webhookService->processEvent($event);
    }

    public function failed(\Throwable $exception): void
    {
        $event = DngWebhookEvent::find($this->eventId);
        $event?->markFailed($exception->getMessage());

        Log::error('DNG webhook job failed permanently', [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage(),
        ]);
    }
}
