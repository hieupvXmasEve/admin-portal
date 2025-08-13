<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNotificationJob implements ShouldQueue
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
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $notificationType,
        protected array $recipients,
        protected array $data = []
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('Processing scheduled notification', [
            'type' => $this->notificationType,
            'recipients_count' => count($this->recipients),
        ]);

        try {
            $result = $notificationService->sendAcademicNotification(
                $this->notificationType,
                $this->recipients,
                $this->data
            );

            Log::info('Scheduled notification processed', [
                'type' => $this->notificationType,
                'sent' => $result['total_sent'] ?? 0,
                'skipped' => $result['total_skipped'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process scheduled notification', [
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
        Log::error('Scheduled notification job failed', [
            'type' => $this->notificationType,
            'recipients_count' => count($this->recipients),
            'error' => $exception->getMessage(),
        ]);
    }
}
