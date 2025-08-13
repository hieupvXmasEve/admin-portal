<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public $timeout = 300; // 5 minutes for bulk processing

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected array $recipients,
        protected string $subject,
        protected string $htmlContent,
        protected ?string $textContent = null,
        protected ?int $templateId = null,
        protected array $attachments = [],
        protected ?int $userId = null,
        protected ?string $batchId = null,
        protected ?int $chunkIndex = null
    ) {
        $this->onQueue('bulk-emails');
    }

    /**
     * Execute the job.
     */
    public function handle(EmailService $emailService): void
    {
        if ($this->batch() && $this->batch()->cancelled()) {
            return;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($this->recipients as $recipient) {
            try {
                // Create email log entry
                $emailLog = EmailLog::create([
                    'recipient' => $recipient,
                    'sender' => config('mail.from.address'),
                    'subject' => $this->subject,
                    'template_id' => $this->templateId,
                    'status' => EmailLog::STATUS_PENDING,
                    'user_id' => $this->userId,
                    'batch_id' => $this->batchId,
                    'metadata' => [
                        'attachments' => array_map(fn($file) => basename($file), $this->attachments),
                        'bulk_send' => true,
                    ],
                ]);

                // Queue individual email job
                dispatch(new SendSingleEmailJob(
                    $emailLog,
                    $this->htmlContent,
                    $this->textContent,
                    $this->attachments
                ));

                $emailLog->markAsQueued();
                $successCount++;

            } catch (\Exception $e) {
                $failureCount++;
                Log::error('Failed to queue bulk email', [
                    'recipient' => $recipient,
                    'batch_id' => $this->batchId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Check if batch is cancelled
            if ($this->batch() && $this->batch()->cancelled()) {
                Log::info('Bulk email batch cancelled', [
                    'batch_id' => $this->batchId,
                    'processed' => $successCount + $failureCount,
                    'total' => count($this->recipients),
                ]);
                return;
            }
        }

        Log::info('Bulk email batch processed', [
            'batch_id' => $this->batchId,
            'chunk_index' => $this->chunkIndex,
            'success' => $successCount,
            'failed' => $failureCount,
            'total' => count($this->recipients),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk email job failed', [
            'batch_id' => $this->batchId,
            'recipients_count' => count($this->recipients),
            'error' => $exception->getMessage(),
        ]);
    }
}
