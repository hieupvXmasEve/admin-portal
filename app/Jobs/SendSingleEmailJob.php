<?php

namespace App\Jobs;

use App\Models\EmailLog;
use App\Models\EmailConfiguration;
use App\Mail\GenericEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class SendSingleEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [
            60,    // 1 minute
            300,   // 5 minutes
            900,   // 15 minutes
            1800,  // 30 minutes
            3600   // 1 hour
        ];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected EmailLog $emailLog,
        protected string $htmlContent,
        protected ?string $textContent = null,
        protected array $attachments = [],
        protected ?int $campusId = null,
    ) {
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Mark email as sending
            $this->emailLog->markAsSending();

            // Resolve campus-specific config — must exist, .env fallback is not allowed
            $config = EmailConfiguration::getActiveForCampus($this->campusId);

            if (! $config) {
                throw new \RuntimeException(
                    'No active EmailConfiguration found for campus_id=' . ($this->campusId ?? 'null') . '. Configure an active email configuration in the database.'
                );
            }

            if (! Config::get('mail.allow_outbound')) {
                Log::warning('Outbound email blocked: MAIL_ALLOW_OUTBOUND is disabled', [
                    'email_log_id' => $this->emailLog->id,
                    'recipient' => $this->emailLog->recipient,
                    'subject' => $this->emailLog->subject,
                ]);
                $this->emailLog->markAsRejected('Outbound email blocked: MAIL_ALLOW_OUTBOUND is disabled');

                return;
            }

            $this->configureMailer($config);

            // Create and send the email
            $email = new GenericEmail(
                $this->emailLog->subject,
                $this->htmlContent,
                $this->textContent,
                $this->attachments
            );

            Mail::to($this->emailLog->recipient)->send($email);

            // Get message ID if available
            $messageId = null;
            if (method_exists(Mail::class, 'getSymfonyMessage')) {
                $symfonyMessage = Mail::getSymfonyMessage();
                if ($symfonyMessage) {
                    $messageId = $symfonyMessage->getMessageId();
                }
            }

            // Mark email as sent
            $this->emailLog->markAsSent($messageId);

            Log::info('Email sent successfully', [
                'email_log_id' => $this->emailLog->id,
                'recipient' => $this->emailLog->recipient,
                'subject' => $this->emailLog->subject,
                'message_id' => $messageId,
            ]);
        } catch (\Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Configure the mailer with SMTP settings
     */
    protected function configureMailer(EmailConfiguration $config): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', $config->toMailConfig());
        Config::set('mail.from.address', $config->from_address);
        Config::set('mail.from.name', $config->from_name);

        // Force Laravel to reload the mail configuration
        app()->forgetInstance('mail.manager');
        app()->forgetInstance('mailer');
    }

    /**
     * Handle job failure with enhanced retry logic
     */
    protected function handleFailure(\Exception $e): void
    {
        $errorMessage = $e->getMessage();
        $currentAttempt = $this->attempts();

        // Categorize the error type
        $errorType = $this->categorizeError($e);

        // Update email log with detailed error information
        $this->emailLog->update([
            'metadata' => array_merge($this->emailLog->metadata ?? [], [
                'last_error' => $errorMessage,
                'error_type' => $errorType,
                'attempt_number' => $currentAttempt,
                'failed_at_attempt' => now()->toISOString(),
            ])
        ]);

        // Check if this is a permanent failure
        if ($this->isPermanentFailure($e)) {
            $this->emailLog->markAsRejected($errorMessage);
            $this->fail($e);
        } else {
            $this->emailLog->markAsFailed($errorMessage);

            // Enhanced retry decision logic
            if ($this->shouldRetry($e, $currentAttempt)) {
                Log::warning('Email sending failed, will retry', [
                    'email_log_id' => $this->emailLog->id,
                    'recipient' => $this->emailLog->recipient,
                    'error' => $errorMessage,
                    'error_type' => $errorType,
                    'attempt' => $currentAttempt,
                    'max_attempts' => $this->tries,
                    'next_retry_in' => $this->getRetryDelay($currentAttempt) . ' seconds',
                ]);

                throw $e; // Let the job retry
            } else {
                Log::error('Email sending permanently failed after all retries', [
                    'email_log_id' => $this->emailLog->id,
                    'recipient' => $this->emailLog->recipient,
                    'error' => $errorMessage,
                    'error_type' => $errorType,
                    'final_attempt' => $currentAttempt,
                ]);

                $this->fail($e);
            }
        }
    }

    /**
     * Categorize error types for better handling
     */
    protected function categorizeError(\Exception $e): string
    {
        $message = strtolower($e->getMessage());

        // Network/Connection errors (usually temporary)
        if (str_contains($message, 'connection') ||
            str_contains($message, 'timeout') ||
            str_contains($message, 'network') ||
            str_contains($message, 'dns')) {
            return 'network';
        }

        // Authentication errors (configuration issue)
        if (str_contains($message, 'authentication') ||
            str_contains($message, 'login') ||
            str_contains($message, 'password') ||
            str_contains($message, 'credentials')) {
            return 'authentication';
        }

        // Rate limiting (temporary)
        if (str_contains($message, 'rate limit') ||
            str_contains($message, 'too many') ||
            str_contains($message, 'quota')) {
            return 'rate_limit';
        }

        // Recipient errors (permanent)
        if (str_contains($message, 'invalid recipient') ||
            str_contains($message, 'mailbox not found') ||
            str_contains($message, 'user unknown')) {
            return 'recipient';
        }

        // Server errors (potentially temporary)
        if (str_contains($message, 'server error') ||
            str_contains($message, '5.') ||
            preg_match('/5\d\d/', $message)) {
            return 'server';
        }

        return 'unknown';
    }

    /**
     * Enhanced retry decision logic
     */
    protected function shouldRetry(\Exception $e, int $currentAttempt): bool
    {
        if ($currentAttempt >= $this->tries) {
            return false;
        }

        $errorType = $this->categorizeError($e);

        // Never retry permanent errors
        if (in_array($errorType, ['recipient', 'authentication'])) {
            return false;
        }

        // Retry network and server errors more aggressively
        if (in_array($errorType, ['network', 'server', 'rate_limit'])) {
            return $currentAttempt < $this->tries;
        }

        // Default retry logic for unknown errors
        return $currentAttempt < ($this->tries - 1); // One less retry for unknown errors
    }

    /**
     * Get retry delay for current attempt
     */
    protected function getRetryDelay(int $attempt): int
    {
        $backoffTimes = $this->backoff();
        return $backoffTimes[min($attempt - 1, count($backoffTimes) - 1)] ?? 3600;
    }

    /**
     * Determine if the exception represents a permanent failure
     */
    protected function isPermanentFailure(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        $permanentErrors = [
            'invalid recipient',
            'invalid email address',
            'mailbox not found',
            'user unknown',
            'address rejected',
            'domain not found',
            'no such user',
        ];

        foreach ($permanentErrors as $error) {
            if (str_contains($message, $error)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Email job permanently failed', [
            'email_log_id' => $this->emailLog->id,
            'recipient' => $this->emailLog->recipient,
            'error' => $exception->getMessage(),
        ]);
    }
}
