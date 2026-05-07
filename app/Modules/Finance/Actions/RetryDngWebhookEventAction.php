<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Dng\Services\DngWebhookService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RetryDngWebhookEventAction
{
    public function __construct(
        protected DngWebhookService $webhookService,
    ) {}

    /**
     * Re-run a DNG webhook event through the processing pipeline.
     *
     * Resets the event back to `received`, increments the attempt counter,
     * and invokes the service synchronously so the operator gets immediate feedback.
     *
     * Refuses to retry events already in `processed` state — those have produced
     * a canonical Payment and re-running would only churn writes.
     *
     * @throws RuntimeException when the event is already in `processed` state.
     */
    public function run(DngWebhookEvent $event): DngWebhookEvent
    {
        if ($event->processing_status === DngWebhookEvent::STATUS_PROCESSED) {
            throw new RuntimeException(
                "Cannot retry webhook event #{$event->id}: already processed."
            );
        }

        $event->update([
            'processing_status' => DngWebhookEvent::STATUS_RECEIVED,
            'error_message' => null,
            'error_category' => null,
            'next_retry_at' => null,
        ]);

        $event->markProcessing();

        try {
            $this->webhookService->processEvent($event);
        } catch (\Throwable $e) {
            Log::error('DNG webhook manual retry failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            $event->markFailedRetryable(
                $e->getMessage(),
                DngWebhookEvent::ERROR_CATEGORY_PROCESSING,
            );

            throw $e;
        }

        return $event->fresh();
    }
}
