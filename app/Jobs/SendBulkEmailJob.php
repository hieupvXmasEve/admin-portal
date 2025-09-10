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
        protected ?string $customBatchId = null,
        protected ?int $chunkIndex = null,
        protected ?array $perRecipientVariables = null,
        protected ?array $globalVariables = null
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
                // Optionally render per-recipient if template is present and variables provided
                $renderedSubject = $this->subject;
                $renderedHtml = $this->htmlContent;
                $renderedText = $this->textContent;

                // Build variables: merge global fallbacks with per-recipient values (per-recipient wins)
                try {
                    $perVars = [];
                    if (is_array($this->perRecipientVariables)) {
                        $perVars = $this->perRecipientVariables[$recipient] ?? [];
                        if (empty($perVars)) {
                            $perVars = $this->perRecipientVariables[strtolower($recipient)] ?? [];
                        }
                    }
                    $vars = array_merge($this->globalVariables ?? [], $perVars ?? []);

                    if (!empty($vars)) {
                        $emailService = app(EmailService::class);
                        $renderedSubject = $emailService->substituteVariables($this->subject, $vars);
                        $renderedHtml = $emailService->substituteVariables($this->htmlContent, $vars);
                        if ($this->textContent) {
                            $renderedText = $emailService->substituteVariables($this->textContent, $vars);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Variable substitution failed; using original content', [
                        'recipient' => $recipient,
                        'batch_id' => $this->customBatchId,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Create email log entry
                $emailLog = EmailLog::create([
                    'recipient' => $recipient,
                    'sender' => config('mail.from.address'),
                    'subject' => $renderedSubject,
                    'template_id' => $this->templateId,
                    'status' => EmailLog::STATUS_PENDING,
                    'user_id' => $this->userId,
                    'batch_id' => $this->customBatchId,
                    'metadata' => [
                        'attachments' => array_map(fn($file) => basename($file), $this->attachments),
                        'bulk_send' => true,
                        'content' => $renderedHtml,
                    ],
                ]);

                // Queue individual email job
                dispatch(new SendSingleEmailJob(
                    $emailLog,
                    $renderedHtml,
                    $renderedText,
                    $this->attachments
                ));

                $emailLog->markAsQueued();
                $successCount++;

            } catch (\Exception $e) {
                $failureCount++;
                Log::error('Failed to queue bulk email', [
                    'recipient' => $recipient,
                    'batch_id' => $this->customBatchId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Check if batch is cancelled
            if ($this->batch() && $this->batch()->cancelled()) {
                Log::info('Bulk email batch cancelled', [
                    'batch_id' => $this->customBatchId,
                    'processed' => $successCount + $failureCount,
                    'total' => count($this->recipients),
                ]);
                return;
            }
        }

        Log::info('Bulk email batch processed', [
            'batch_id' => $this->customBatchId,
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
            'batch_id' => $this->customBatchId,
            'recipients_count' => count($this->recipients),
            'error' => $exception->getMessage(),
        ]);
    }
}
