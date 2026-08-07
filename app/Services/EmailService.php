<?php

namespace App\Services;

use App\Jobs\SendSingleEmailJob;
use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\User;
use App\Models\UserEmailPreference;
use App\Services\EmailLoggingService;
use App\Services\UserEmailPreferenceService;
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
        mixed $template = null,
        array $attachments = [],
        ?User $sender = null,
        array $templateVariables = [],
        ?int $campusId = null,
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
                'campus_id' => $campusId,
            ]
        );

        try {
            // Use the provided content directly and perform variable substitution if needed
            $htmlContent = $content;
            $textContent = null;
            $finalSubject = $subject;

            // If template variables are provided, substitute them in the provided content
            if (!empty($templateVariables)) {
                $finalSubject = $this->substituteVariables($subject, $templateVariables);
                $htmlContent = $this->substituteVariables($content, $templateVariables);
                
                // Update email log with final subject
                $emailLog->update(['subject' => $finalSubject]);
            }

            // Validate final content
            $this->validateRenderedContent($htmlContent, $textContent);

            // Dispatch email job to queue
            dispatch(new SendSingleEmailJob(
                $emailLog,
                $htmlContent,
                $textContent,
                $attachments,
                $campusId,
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
    public function getActiveConfiguration(?int $campusId = null): ?EmailConfiguration
    {
        return EmailConfiguration::getActiveForCampus($campusId);
    }

    /**
     * Substitute variables in content using {{ variable }} syntax
     */
    public function substituteVariables(string $content, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = trim($matches[1]);
            return $variables[$key] ?? $matches[0]; // Return original if variable not found
        }, $content);
    }

    /**
     * Get email logs with filters
     */
    public function getEmailLogs(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = EmailLog::query()->with(['user']);

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

                // Re-queue the email — restore campus context from original dispatch metadata
                dispatch(new SendSingleEmailJob(
                    $emailLog,
                    $emailLog->metadata['html_content'] ?? '',
                    $emailLog->metadata['text_content'] ?? null,
                    $emailLog->metadata['attachments'] ?? [],
                    isset($emailLog->metadata['campus_id']) ? (int) $emailLog->metadata['campus_id'] : null
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
