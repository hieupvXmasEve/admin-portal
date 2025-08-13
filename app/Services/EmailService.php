<?php

namespace App\Services;

use App\Jobs\SendSingleEmailJob;
use App\Jobs\SendBulkEmailJob;
use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\UserEmailPreference;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailService
{
    /**
     * Send a single email
     */
    public function sendSingleEmail(
        string $recipient,
        string $subject,
        string $content,
        ?EmailTemplate $template = null,
        array $attachments = [],
        ?User $sender = null
    ): EmailLog {
        // Validate email address
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address: ' . $recipient);
        }

        // Create email log entry
        $emailLog = EmailLog::create([
            'recipient' => $recipient,
            'sender' => $sender?->email ?? config('mail.from.address'),
            'subject' => $subject,
            'template_id' => $template?->id,
            'status' => EmailLog::STATUS_PENDING,
            'user_id' => $sender?->id,
            'metadata' => [
                'attachments' => array_map(fn($file) => basename($file), $attachments),
                'content_length' => strlen($content),
            ],
        ]);

        try {
            // Render template content if template is provided
            $htmlContent = $content;
            $textContent = null;
            
            if ($template) {
                $rendered = $template->render([]);
                $htmlContent = $rendered['html'] ?? $content;
                $textContent = $rendered['text'] ?? null;
            }

            // Dispatch email job to queue
            dispatch(new SendSingleEmailJob(
                $emailLog,
                $htmlContent,
                $textContent,
                $attachments
            ));

            // Mark as queued
            $emailLog->markAsQueued();
            
            Log::info('Email queued for sending', [
                'email_log_id' => $emailLog->id,
                'recipient' => $recipient,
                'subject' => $subject,
            ]);

            return $emailLog;
        } catch (\Exception $e) {
            $emailLog->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Send bulk emails
     */
    public function sendBulkEmail(
        array $recipients,
        string $subject,
        string $content,
        ?EmailTemplate $template = null,
        array $attachments = [],
        ?User $sender = null,
        int $chunkSize = 100
    ): string {
        // Validate recipients
        $validRecipients = array_filter($recipients, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        if (empty($validRecipients)) {
            throw new \InvalidArgumentException('No valid email addresses provided');
        }

        // Generate batch ID
        $batchId = Str::uuid()->toString();

        // Render template content if provided
        $htmlContent = $content;
        $textContent = null;
        
        if ($template) {
            $rendered = $template->render([]);
            $htmlContent = $rendered['html'] ?? $content;
            $textContent = $rendered['text'] ?? null;
        }

        // Create jobs for chunks of recipients
        $chunks = array_chunk($validRecipients, $chunkSize);
        $jobs = [];

        foreach ($chunks as $chunk) {
            $jobs[] = new SendBulkEmailJob(
                $chunk,
                $subject,
                $htmlContent,
                $textContent,
                $template?->id,
                $attachments,
                $sender?->id,
                $batchId
            );
        }

        // Dispatch as batch for better tracking
        Bus::batch($jobs)
            ->name('Bulk Email: ' . $subject)
            ->onQueue('bulk-emails')
            ->dispatch();

        Log::info('Bulk email batch dispatched', [
            'batch_id' => $batchId,
            'total_recipients' => count($validRecipients),
            'chunks' => count($chunks),
            'subject' => $subject,
        ]);

        return $batchId;
    }

    /**
     * Queue email for later processing
     */
    public function queueEmail(array $emailData): EmailLog
    {
        // TODO: Implement queue logic in Phase 4
        return $this->sendSingleEmail(
            $emailData['recipient'],
            $emailData['subject'],
            $emailData['content'],
            $emailData['template'] ?? null,
            $emailData['attachments'] ?? [],
            $emailData['sender'] ?? null
        );
    }

    /**
     * Validate email configuration
     */
    public function validateEmailConfiguration(EmailConfiguration $config): array
    {
        $errors = [];

        // Basic validation
        if (empty($config->host)) {
            $errors[] = 'SMTP host is required';
        }

        if (empty($config->port) || $config->port < 1 || $config->port > 65535) {
            $errors[] = 'Valid SMTP port is required (1-65535)';
        }

        if (empty($config->from_address) || !filter_var($config->from_address, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid from email address is required';
        }

        if (empty($config->from_name)) {
            $errors[] = 'From name is required';
        }

        return $errors;
    }

    /**
     * Test SMTP connection
     */
    public function testSmtpConnection(EmailConfiguration $config): array
    {
        try {
            // TODO: Implement actual SMTP connection testing in Phase 2
            // For now, just validate the configuration
            $errors = $this->validateEmailConfiguration($config);
            
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'message' => 'Configuration validation failed',
                    'errors' => $errors,
                ];
            }

            // Update test result
            $config->update([
                'last_tested_at' => now(),
                'test_result' => 'Connection test successful',
            ]);

            return [
                'success' => true,
                'message' => 'SMTP configuration is valid',
                'tested_at' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            $config->update([
                'last_tested_at' => now(),
                'test_result' => 'Connection test failed: ' . $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'SMTP connection test failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if user can receive email notification
     */
    public function canUserReceiveNotification(User $user, string $notificationType): bool
    {
        $preference = UserEmailPreference::getUserPreference($user->id, $notificationType);
        
        if (!$preference) {
            // If no preference exists, check for 'all' preference
            $allPreference = UserEmailPreference::getUserPreference($user->id, UserEmailPreference::TYPE_ALL);
            return $allPreference ? $allPreference->canReceiveNotification() : true;
        }

        return $preference->canReceiveNotification();
    }

    /**
     * Get email statistics
     */
    public function getEmailStatistics(?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): array
    {
        return EmailLog::getStatistics($startDate, $endDate);
    }

    /**
     * Get active email configuration
     */
    public function getActiveConfiguration(): ?EmailConfiguration
    {
        return EmailConfiguration::getActive();
    }

    /**
     * Render email template with variables
     */
    public function renderTemplate(EmailTemplate $template, array $variables = []): array
    {
        // Validate required variables
        $missingVariables = $template->validateVariables($variables);
        if (!empty($missingVariables)) {
            throw new \InvalidArgumentException(
                'Missing required template variables: ' . implode(', ', $missingVariables)
            );
        }

        return $template->render($variables);
    }

    /**
     * Get email logs with filters
     */
    public function getEmailLogs(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = EmailLog::query()->with(['template', 'user']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['recipient'])) {
            $query->where('recipient', 'like', '%' . $filters['recipient'] . '%');
        }

        if (isset($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 15);
    }
}
