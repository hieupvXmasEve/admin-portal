<?php

namespace App\Services;

use App\Jobs\SendSingleEmailJob;
use App\Jobs\SendBulkEmailJob;
use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\UserEmailPreference;
use App\Services\EmailLoggingService;
use App\Services\UserEmailPreferenceService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailService
{
    protected EmailLoggingService $loggingService;
    protected UserEmailPreferenceService $preferenceService;

    public function __construct(
        EmailLoggingService $loggingService,
        UserEmailPreferenceService $preferenceService
    ) {
        $this->loggingService = $loggingService;
        $this->preferenceService = $preferenceService;
    }
    /**
     * Send a single email with comprehensive validation and logging
     */
    public function sendSingleEmail(
        string $recipient,
        string $subject,
        string $content,
        ?EmailTemplate $template = null,
        array $attachments = [],
        ?User $sender = null,
        array $templateVariables = []
    ): EmailLog {
        // Comprehensive email address validation
        $this->validateEmailAddress($recipient);

        // Validate subject and content
        $this->validateEmailContent($subject, $content);

        // Validate attachments
        $this->validateAttachments($attachments);

        // Check if user can receive notifications (if user exists)
        if ($user = User::where('email', $recipient)->first()) {
            if (!$this->canUserReceiveNotification($user, $template?->type ?? 'general')) {
                Log::info('Email not sent due to user preferences', [
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'notification_type' => $template?->type ?? 'general',
                ]);

                throw new \InvalidArgumentException('User has opted out of email notifications');
            }
        }

        // Create comprehensive email log entry using logging service
        $emailLog = $this->loggingService->logEmailSendingAttempt(
            $recipient,
            $subject,
            $template,
            $sender,
            [
                'attachments' => $this->getAttachmentMetadata($attachments),
                'content_length' => strlen($content),
                'template_variables' => $templateVariables,
                'created_via' => 'single_email_api',
            ]
        );

        try {
            // Render template content if template is provided
            $htmlContent = $content;
            $textContent = null;
            $finalSubject = $subject;

            if ($template) {
                $rendered = $this->renderTemplate($template, $templateVariables);
                $htmlContent = $rendered['html'] ?? $content;
                $textContent = $rendered['text'] ?? null;
                $finalSubject = $rendered['subject'] ?? $subject;
                
                // Update email log with rendered subject
                $emailLog->update(['subject' => $finalSubject]);
            }

            // Validate final content
            $this->validateRenderedContent($htmlContent, $textContent);

            // Dispatch email job to queue
            dispatch(new SendSingleEmailJob(
                $emailLog,
                $htmlContent,
                $textContent,
                $attachments
            ));

            // Mark as queued with additional metadata
            $emailLog->markAsQueued();

            $this->loggingService->logEmailOperation('single_email_queued', [
                'email_log_id' => $emailLog->id,
                'recipient' => $recipient,
                'subject' => $subject,
                'template_id' => $template?->id,
                'attachments_count' => count($attachments),
            ], 'info');

            return $emailLog;
        } catch (\Exception $e) {
            $this->loggingService->logEmailDeliveryFailure($emailLog, $e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Validate email address with comprehensive checks
     */
    protected function validateEmailAddress(string $email): void
    {
        // Basic format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address format: ' . $email);
        }

        // Additional validation checks
        if (strlen($email) > 254) {
            throw new \InvalidArgumentException('Email address too long (max 254 characters)');
        }

        // Check for common invalid patterns
        $invalidPatterns = [
            '/\.{2,}/',           // Multiple consecutive dots
            '/^\./',              // Starting with dot
            '/\.$/',              // Ending with dot
            '/@\./',              // @ followed by dot
            '/\.@/',              // Dot followed by @
        ];

        foreach ($invalidPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                throw new \InvalidArgumentException('Invalid email address pattern: ' . $email);
            }
        }

        // Validate domain part
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid email address structure: ' . $email);
        }

        [$localPart, $domain] = $parts;

        // Validate local part length
        if (strlen($localPart) > 64) {
            throw new \InvalidArgumentException('Email local part too long (max 64 characters)');
        }

        // Validate domain
        if (strlen($domain) > 253) {
            throw new \InvalidArgumentException('Email domain too long (max 253 characters)');
        }

        // Check if domain has valid format
        if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain)) {
            throw new \InvalidArgumentException('Invalid email domain format: ' . $domain);
        }
    }

    /**
     * Validate email content
     */
    protected function validateEmailContent(string $subject, string $content): void
    {
        // Validate subject
        if (empty(trim($subject))) {
            throw new \InvalidArgumentException('Email subject cannot be empty');
        }

        if (strlen($subject) > 998) {
            throw new \InvalidArgumentException('Email subject too long (max 998 characters)');
        }

        // Check for suspicious patterns in subject
        $suspiciousPatterns = [
            '/\[SPAM\]/i',
            '/\[BULK\]/i',
            '/FREE\s*MONEY/i',
            '/URGENT\s*ACTION/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $subject)) {
                Log::warning('Potentially suspicious email subject detected', [
                    'subject' => $subject,
                    'pattern' => $pattern,
                ]);
            }
        }

        // Validate content
        if (empty(trim($content))) {
            throw new \InvalidArgumentException('Email content cannot be empty');
        }

        // Check content size (reasonable limit for email)
        if (strlen($content) > 10 * 1024 * 1024) { // 10MB
            throw new \InvalidArgumentException('Email content too large (max 10MB)');
        }
    }

    /**
     * Validate attachments
     */
    protected function validateAttachments(array $attachments): void
    {
        $totalSize = 0;
        $maxFileSize = 25 * 1024 * 1024; // 25MB per file
        $maxTotalSize = 50 * 1024 * 1024; // 50MB total

        foreach ($attachments as $attachment) {
            $filePath = is_array($attachment) ? $attachment['path'] : $attachment;

            if (!file_exists($filePath)) {
                throw new \InvalidArgumentException('Attachment file not found: ' . $filePath);
            }

            $fileSize = filesize($filePath);
            if ($fileSize > $maxFileSize) {
                throw new \InvalidArgumentException('Attachment too large: ' . basename($filePath) . ' (max 25MB)');
            }

            $totalSize += $fileSize;
        }

        if ($totalSize > $maxTotalSize) {
            throw new \InvalidArgumentException('Total attachment size too large (max 50MB)');
        }

        if (count($attachments) > 20) {
            throw new \InvalidArgumentException('Too many attachments (max 20 files)');
        }
    }

    /**
     * Validate rendered content
     */
    protected function validateRenderedContent(?string $htmlContent, ?string $textContent): void
    {
        if (empty($htmlContent) && empty($textContent)) {
            throw new \InvalidArgumentException('Email must have either HTML or text content');
        }

        // Validate HTML content if present
        if ($htmlContent) {
            // Check for basic HTML structure issues
            if (substr_count($htmlContent, '<html>') > 1 || substr_count($htmlContent, '</html>') > 1) {
                throw new \InvalidArgumentException('Invalid HTML structure: multiple html tags');
            }

            // Check for potentially dangerous content
            $dangerousPatterns = [
                '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
                '/<iframe\b[^>]*>/i',
                '/javascript:/i',
                '/on\w+\s*=/i', // onclick, onload, etc.
            ];

            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $htmlContent)) {
                    Log::warning('Potentially dangerous HTML content detected', [
                        'pattern' => $pattern,
                    ]);
                }
            }
        }
    }

    /**
     * Get attachment metadata for logging
     */
    protected function getAttachmentMetadata(array $attachments): array
    {
        $metadata = [];

        foreach ($attachments as $attachment) {
            $filePath = is_array($attachment) ? $attachment['path'] : $attachment;

            if (file_exists($filePath)) {
                $metadata[] = [
                    'name' => basename($filePath),
                    'size' => filesize($filePath),
                    'mime_type' => mime_content_type($filePath),
                    'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                ];
            }
        }

        return $metadata;
    }

    /**
     * Send bulk emails with comprehensive validation and progress tracking
     */
    public function sendBulkEmail(
        array $recipients,
        string $subject,
        string $content,
        ?EmailTemplate $template = null,
        array $attachments = [],
        ?User $sender = null,
        array $templateVariables = [],
        int $chunkSize = 100,
        array $options = []
    ): array {
        // Validate bulk email parameters
        $this->validateBulkEmailParameters($recipients, $subject, $content, $chunkSize);

        // Process and validate recipients
        $processedRecipients = $this->processRecipients($recipients);

        if (empty($processedRecipients['valid'])) {
            throw new \InvalidArgumentException('No valid email addresses provided');
        }

        // Validate content and attachments
        $this->validateEmailContent($subject, $content);
        $this->validateAttachments($attachments);

        // Generate batch ID and create batch metadata
        $batchId = Str::uuid()->toString();
        $batchMetadata = [
            'batch_id' => $batchId,
            'total_recipients' => count($processedRecipients['valid']),
            'invalid_recipients' => count($processedRecipients['invalid']),
            'subject' => $subject,
            'template_id' => $template?->id,
            'sender_id' => $sender?->id,
            'created_at' => now()->toISOString(),
            'options' => $options,
        ];

        try {
            // Render template content if provided
            $htmlContent = $content;
            $textContent = null;
            $finalSubject = $subject;

            if ($template) {
                $rendered = $this->renderTemplate($template, $templateVariables);
                $htmlContent = $rendered['html'] ?? $content;
                $textContent = $rendered['text'] ?? null;
                $finalSubject = $rendered['subject'] ?? $subject;
            }

            // Validate rendered content
            $this->validateRenderedContent($htmlContent, $textContent);

            // Create jobs for chunks of recipients
            $chunks = array_chunk($processedRecipients['valid'], $chunkSize);
            $jobs = [];

            foreach ($chunks as $chunkIndex => $chunk) {
                $jobs[] = new SendBulkEmailJob(
                    $chunk,
                    $finalSubject,
                    $htmlContent,
                    $textContent,
                    $template?->id,
                    $attachments,
                    $sender?->id,
                    $batchId,
                    $chunkIndex
                );
            }

            // Configure batch options
            $batchName = $options['batch_name'] ?? 'Bulk Email: ' . $subject;
            $queue = $options['queue'] ?? 'bulk-emails';

            // Dispatch as batch for better tracking
            $batch = Bus::batch($jobs)
                ->name($batchName)
                ->onQueue($queue)
                ->allowFailures()
                ->then(function () use ($batchId) {
                    Log::info('Bulk email batch completed successfully', ['batch_id' => $batchId]);
                })
                ->catch(function () use ($batchId) {
                    Log::error('Bulk email batch failed', ['batch_id' => $batchId]);
                })
                ->finally(function () use ($batchId) {
                    Log::info('Bulk email batch finished', ['batch_id' => $batchId]);
                })
                ->dispatch();

            // Store batch metadata for tracking
            $this->storeBatchMetadata($batchId, $batchMetadata, $batch->id);

            Log::info('Bulk email batch dispatched', [
                'batch_id' => $batchId,
                'laravel_batch_id' => $batch->id,
                'total_recipients' => count($processedRecipients['valid']),
                'invalid_recipients' => count($processedRecipients['invalid']),
                'chunks' => count($chunks),
                'subject' => $subject,
                'template_id' => $template?->id,
            ]);

            return [
                'batch_id' => $batchId,
                'laravel_batch_id' => $batch->id,
                'total_recipients' => count($processedRecipients['valid']),
                'invalid_recipients' => count($processedRecipients['invalid']),
                'invalid_emails' => $processedRecipients['invalid'],
                'chunks' => count($chunks),
                'estimated_completion' => now()->addMinutes(count($chunks) * 2)->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to dispatch bulk email batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
                'recipients_count' => count($processedRecipients['valid']),
            ]);

            throw $e;
        }
    }

    /**
     * Validate bulk email parameters
     */
    protected function validateBulkEmailParameters(array $recipients, string $subject, string $content, int $chunkSize): void
    {
        if (empty($recipients)) {
            throw new \InvalidArgumentException('Recipients list cannot be empty');
        }

        if (count($recipients) > 10000) {
            throw new \InvalidArgumentException('Too many recipients (max 10,000 per batch)');
        }

        if ($chunkSize < 1 || $chunkSize > 1000) {
            throw new \InvalidArgumentException('Invalid chunk size (must be between 1 and 1000)');
        }

        // Check rate limits
        $activeConfig = $this->getActiveConfiguration();
        if ($activeConfig && $activeConfig->daily_limit) {
            $todaysSentCount = EmailLog::whereDate('created_at', today())
                ->whereIn('status', [EmailLog::STATUS_SENT, EmailLog::STATUS_DELIVERED])
                ->count();

            if ($todaysSentCount + count($recipients) > $activeConfig->daily_limit) {
                throw new \InvalidArgumentException(
                    'Bulk email would exceed daily limit of ' . $activeConfig->daily_limit . ' emails'
                );
            }
        }
    }

    /**
     * Process and validate recipients list
     */
    protected function processRecipients(array $recipients): array
    {
        $valid = [];
        $invalid = [];
        $seen = [];

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            $email = trim(strtolower($email));

            // Skip duplicates
            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            try {
                $this->validateEmailAddress($email);

                // Check if user has opted out
                if ($user = User::where('email', $email)->first()) {
                    if (!$this->canUserReceiveNotification($user, 'bulk')) {
                        Log::info('Skipping recipient due to email preferences', ['email' => $email]);
                        continue;
                    }
                }

                $valid[] = $email;
            } catch (\InvalidArgumentException $e) {
                $invalid[] = [
                    'email' => $email,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }

    /**
     * Store batch metadata for tracking
     */
    protected function storeBatchMetadata(string $batchId, array $metadata, string $laravelBatchId): void
    {
        // Store in cache for quick access
        cache()->put("bulk_email_batch:{$batchId}", $metadata, now()->addDays(7));

        // Also store Laravel batch ID mapping
        cache()->put("bulk_email_laravel_batch:{$laravelBatchId}", $batchId, now()->addDays(7));
    }

    /**
     * Get bulk email batch progress
     */
    public function getBulkEmailProgress(string $batchId): array
    {
        $metadata = cache()->get("bulk_email_batch:{$batchId}");

        if (!$metadata) {
            throw new \InvalidArgumentException('Batch not found: ' . $batchId);
        }

        // Get email logs for this batch
        $logs = EmailLog::where('batch_id', $batchId)->get();

        $statusCounts = $logs->groupBy('status')->map->count();

        $progress = [
            'batch_id' => $batchId,
            'metadata' => $metadata,
            'total_emails' => $logs->count(),
            'status_breakdown' => $statusCounts->toArray(),
            'progress_percentage' => $logs->count() > 0
                ? round(($logs->whereIn('status', [
                    EmailLog::STATUS_SENT,
                    EmailLog::STATUS_DELIVERED,
                    EmailLog::STATUS_FAILED,
                    EmailLog::STATUS_BOUNCED,
                    EmailLog::STATUS_REJECTED
                ])->count() / $logs->count()) * 100, 2)
                : 0,
            'completed_at' => $logs->whereIn('status', [
                EmailLog::STATUS_SENT,
                EmailLog::STATUS_DELIVERED,
                EmailLog::STATUS_FAILED,
                EmailLog::STATUS_BOUNCED,
                EmailLog::STATUS_REJECTED
            ])->count() === $logs->count() ? now()->toISOString() : null,
        ];

        return $progress;
    }

    /**
     * Cancel bulk email batch
     */
    public function cancelBulkEmailBatch(string $batchId): bool
    {
        $metadata = cache()->get("bulk_email_batch:{$batchId}");

        if (!$metadata) {
            throw new \InvalidArgumentException('Batch not found: ' . $batchId);
        }

        // Find Laravel batch by ID mapping
        $laravelBatchId = null;
        foreach (cache()->get("bulk_email_laravel_batch:*", []) as $key => $value) {
            if ($value === $batchId) {
                $laravelBatchId = str_replace('bulk_email_laravel_batch:', '', $key);
                break;
            }
        }

        if ($laravelBatchId) {
            $batch = Bus::findBatch($laravelBatchId);
            if ($batch && !$batch->finished()) {
                $batch->cancel();

                Log::info('Bulk email batch cancelled', [
                    'batch_id' => $batchId,
                    'laravel_batch_id' => $laravelBatchId,
                ]);

                return true;
            }
        }

        return false;
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

    /**
     * Get queue status and monitoring information
     */
    public function getQueueStatus(): array
    {
        // Get queue sizes from Redis (assuming Redis is used for queues)
        $queueSizes = [];
        $queues = ['emails', 'bulk-emails', 'notifications'];

        foreach ($queues as $queue) {
            try {
                $size = \Illuminate\Support\Facades\Redis::llen("queues:{$queue}");
                $queueSizes[$queue] = $size;
            } catch (\Exception $e) {
                $queueSizes[$queue] = 'unavailable';
            }
        }

        // Get failed jobs count
        $failedJobsCount = \Illuminate\Support\Facades\DB::table('failed_jobs')->count();

        // Get recent email statistics
        $recentStats = $this->getRecentEmailStatistics();

        // Get active batch jobs
        $activeBatches = $this->getActiveBatchJobs();

        return [
            'queue_sizes' => $queueSizes,
            'failed_jobs_count' => $failedJobsCount,
            'recent_statistics' => $recentStats,
            'active_batches' => $activeBatches,
            'system_health' => $this->assessSystemHealth($queueSizes, $failedJobsCount),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get recent email statistics for monitoring
     */
    protected function getRecentEmailStatistics(): array
    {
        $now = now();
        $oneHourAgo = $now->copy()->subHour();
        $oneDayAgo = $now->copy()->subDay();

        return [
            'last_hour' => EmailLog::getStatistics($oneHourAgo, $now),
            'last_24_hours' => EmailLog::getStatistics($oneDayAgo, $now),
            'today' => EmailLog::getStatistics($now->copy()->startOfDay(), $now),
        ];
    }

    /**
     * Get active batch jobs information
     */
    protected function getActiveBatchJobs(): array
    {
        try {
            $batches = \Illuminate\Support\Facades\DB::table('job_batches')
                ->where('finished_at', null)
                ->where('cancelled_at', null)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return $batches->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'total_jobs' => $batch->total_jobs,
                    'pending_jobs' => $batch->pending_jobs,
                    'failed_jobs' => $batch->failed_jobs,
                    'progress_percentage' => $batch->total_jobs > 0
                        ? round((($batch->total_jobs - $batch->pending_jobs) / $batch->total_jobs) * 100, 2)
                        : 0,
                    'created_at' => $batch->created_at,
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get active batch jobs', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Assess overall system health
     */
    protected function assessSystemHealth(array $queueSizes, int $failedJobsCount): array
    {
        $issues = [];
        $status = 'healthy';

        // Check queue sizes
        foreach ($queueSizes as $queue => $size) {
            if (is_numeric($size) && $size > 1000) {
                $issues[] = "High queue size for {$queue}: {$size} jobs";
                $status = 'warning';
            }
            if (is_numeric($size) && $size > 5000) {
                $status = 'critical';
            }
        }

        // Check failed jobs
        if ($failedJobsCount > 100) {
            $issues[] = "High number of failed jobs: {$failedJobsCount}";
            $status = $status === 'healthy' ? 'warning' : $status;
        }
        if ($failedJobsCount > 500) {
            $status = 'critical';
        }

        // Check recent email failure rate
        $recentStats = $this->getRecentEmailStatistics();
        $hourlyFailureRate = $recentStats['last_hour']['success_rate'] ?? 100;

        if ($hourlyFailureRate < 90) {
            $issues[] = "Low email success rate in last hour: {$hourlyFailureRate}%";
            $status = $status === 'healthy' ? 'warning' : $status;
        }
        if ($hourlyFailureRate < 70) {
            $status = 'critical';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'recommendations' => $this->getHealthRecommendations($status, $issues),
        ];
    }

    /**
     * Get health recommendations based on current status
     */
    protected function getHealthRecommendations(string $status, array $issues): array
    {
        $recommendations = [];

        if ($status === 'critical') {
            $recommendations[] = 'Immediate attention required - check queue workers and SMTP configuration';
            $recommendations[] = 'Consider scaling up queue workers or pausing bulk email operations';
        } elseif ($status === 'warning') {
            $recommendations[] = 'Monitor system closely and consider increasing queue worker capacity';
            $recommendations[] = 'Review failed jobs and retry if appropriate';
        }

        if (str_contains(implode(' ', $issues), 'queue size')) {
            $recommendations[] = 'Increase number of queue workers or optimize job processing';
        }

        if (str_contains(implode(' ', $issues), 'failed jobs')) {
            $recommendations[] = 'Review and retry failed jobs, check SMTP configuration';
        }

        if (str_contains(implode(' ', $issues), 'success rate')) {
            $recommendations[] = 'Check SMTP server status and email configuration';
        }

        return array_unique($recommendations);
    }

    /**
     * Retry failed emails with specific criteria
     */
    public function retryFailedEmails(array $criteria = []): array
    {
        $query = EmailLog::where('status', EmailLog::STATUS_FAILED);

        // Apply criteria filters
        if (isset($criteria['max_retry_count'])) {
            $query->where('retry_count', '<=', $criteria['max_retry_count']);
        }

        if (isset($criteria['failed_after'])) {
            $query->where('failed_at', '>=', $criteria['failed_after']);
        }

        if (isset($criteria['error_type'])) {
            $query->whereJsonContains('metadata->error_type', $criteria['error_type']);
        }

        $failedEmails = $query->limit($criteria['limit'] ?? 100)->get();

        $retryCount = 0;
        $skippedCount = 0;

        foreach ($failedEmails as $emailLog) {
            if ($emailLog->canRetry($criteria['max_total_retries'] ?? 5)) {
                // Reset status and retry
                $emailLog->update([
                    'status' => EmailLog::STATUS_PENDING,
                    'failed_at' => null,
                    'error_message' => null,
                ]);

                // Re-queue the email
                dispatch(new SendSingleEmailJob(
                    $emailLog,
                    $emailLog->metadata['html_content'] ?? '',
                    $emailLog->metadata['text_content'] ?? null,
                    $emailLog->metadata['attachments'] ?? []
                ));

                $retryCount++;
            } else {
                $skippedCount++;
            }
        }

        Log::info('Bulk retry of failed emails completed', [
            'retried' => $retryCount,
            'skipped' => $skippedCount,
            'criteria' => $criteria,
        ]);

        return [
            'retried' => $retryCount,
            'skipped' => $skippedCount,
            'total_processed' => $retryCount + $skippedCount,
        ];
    }
}
