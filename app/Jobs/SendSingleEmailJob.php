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
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected EmailLog $emailLog,
        protected string $htmlContent,
        protected ?string $textContent = null,
        protected array $attachments = []
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

            // Get active email configuration
            $config = EmailConfiguration::getActive();
            
            if (!$config) {
                throw new \Exception('No active email configuration found');
            }

            // Configure mail settings dynamically
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
     * Handle job failure
     */
    protected function handleFailure(\Exception $e): void
    {
        $errorMessage = $e->getMessage();

        // Check if this is a permanent failure
        if ($this->isPermanentFailure($e)) {
            $this->emailLog->markAsRejected($errorMessage);
            $this->fail($e);
        } else {
            $this->emailLog->markAsFailed($errorMessage);
            
            // Check if we should retry
            if (!$this->emailLog->canRetry($this->tries)) {
                $this->fail($e);
            } else {
                throw $e; // Let the job retry
            }
        }

        Log::error('Email sending failed', [
            'email_log_id' => $this->emailLog->id,
            'recipient' => $this->emailLog->recipient,
            'error' => $errorMessage,
            'retry_count' => $this->emailLog->retry_count,
        ]);
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
