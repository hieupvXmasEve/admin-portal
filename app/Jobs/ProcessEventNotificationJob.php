<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use App\Services\EventNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEventNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $eventId,
        protected string $notificationType,
        protected array $data = []
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(EventNotificationService $eventNotificationService): void
    {
        Log::info('Processing event notification job', [
            'event_id' => $this->eventId,
            'type' => $this->notificationType,
        ]);

        try {
            $event = Event::find($this->eventId);

            if (! $event) {
                Log::warning('Event not found for notification job', [
                    'event_id' => $this->eventId,
                    'type' => $this->notificationType,
                ]);

                return;
            }

            match ($this->notificationType) {
                'publication' => $eventNotificationService->notifyEventPublication($event),
                'cancellation' => $eventNotificationService->notifyEventCancellation(
                    $event,
                    $this->data['reason'] ?? null
                ),
                'completion' => $eventNotificationService->notifyEventCompletion($event),
                default => Log::warning('Unknown notification type', [
                    'type' => $this->notificationType,
                    'event_id' => $this->eventId,
                ])
            };

            Log::info('Event notification job completed', [
                'event_id' => $this->eventId,
                'type' => $this->notificationType,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process event notification job', [
                'event_id' => $this->eventId,
                'type' => $this->notificationType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Event notification job failed', [
            'event_id' => $this->eventId,
            'type' => $this->notificationType,
            'error' => $exception->getMessage(),
        ]);
    }
}
