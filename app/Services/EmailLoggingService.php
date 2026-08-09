<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailLoggingService
{
    /**
     * Log email operation with comprehensive details
     */
    public function logEmailOperation(
        string $operation,
        array $data,
        ?string $status = null,
        ?string $errorMessage = null,
        array $metadata = []
    ): void {
        $logData = [
            'operation' => $operation,
            'status' => $status ?? 'info',
            'data' => $data,
            'metadata' => array_merge($metadata, [
                'timestamp' => now()->toISOString(),
                'user_id' => auth()->id(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'session_id' => session()->getId(),
            ]),
        ];

        if ($errorMessage) {
            $logData['error_message'] = $errorMessage;
        }

        // Log to Laravel log with appropriate level
        $logLevel = $this->getLogLevel($status);
        Log::channel('email')->{$logLevel}("Email Operation: {$operation}", $logData);

        // Store detailed operation log in database if it's a significant operation
        if ($this->shouldStoreInDatabase($operation)) {
            $this->storeOperationLog($operation, $logData);
        }
    }

    /**
     * Log email sending attempt with detailed information
     */
    public function logEmailSendingAttempt(
        string $recipient,
        string $subject,
        mixed $template = null,
        ?User $sender = null,
        array $metadata = []
    ): EmailLog {
        return EmailLog::create([
            'recipient' => $recipient,
            'sender' => $sender?->email ?? config('mail.from.address'),
            'subject' => $subject,
            'template_id' => $template?->id,
            'status' => EmailLog::STATUS_PENDING,
            'user_id' => $sender?->id,
            'metadata' => array_merge($metadata, [
                'attempt_timestamp' => now()->toISOString(),
                'sender_ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'session_id' => session()->getId(),
            ]),
        ]);
    }

    /**
     * Log email delivery success with timing information
     */
    public function logEmailDeliverySuccess(
        EmailLog $emailLog,
        ?string $messageId = null,
        array $deliveryMetadata = []
    ): void {
        $emailLog->markAsSent($messageId);

        $this->logEmailOperation('email_delivered_successfully', [
            'email_log_id' => $emailLog->id,
            'recipient' => $emailLog->recipient,
            'subject' => $emailLog->subject,
            'message_id' => $messageId,
            'delivery_time_seconds' => $emailLog->sent_at ?
                now()->diffInSeconds($emailLog->created_at) : null,
        ], 'info', null, array_merge($deliveryMetadata, [
            'delivery_method' => 'smtp',
            'retry_count' => $emailLog->retry_count,
        ]));
    }

    /**
     * Log email delivery failure with detailed error information
     */
    public function logEmailDeliveryFailure(
        EmailLog $emailLog,
        string $errorMessage,
        ?\Exception $exception = null,
        array $failureMetadata = []
    ): void {
        $emailLog->markAsFailed($errorMessage);

        $errorDetails = [
            'email_log_id' => $emailLog->id,
            'recipient' => $emailLog->recipient,
            'subject' => $emailLog->subject,
            'error_message' => $errorMessage,
            'retry_count' => $emailLog->retry_count,
            'failure_timestamp' => now()->toISOString(),
        ];

        if ($exception) {
            $errorDetails['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $this->logEmailOperation('email_delivery_failed', $errorDetails, 'error', $errorMessage,
            array_merge($failureMetadata, [
                'failure_category' => $this->categorizeFailure($errorMessage),
                'can_retry' => $emailLog->canRetry(),
                'next_retry_at' => $emailLog->canRetry() ?
                    now()->addMinutes(pow(2, $emailLog->retry_count))->toISOString() : null,
            ])
        );

        // Alert if this is a critical failure or high retry count
        if ($emailLog->retry_count >= 2 || $this->isCriticalFailure($errorMessage)) {
            $this->alertCriticalEmailFailure($emailLog, $errorMessage);
        }
    }

    /**
     * Log SMTP configuration changes
     */
    public function logSmtpConfigurationChange(
        array $oldConfig,
        array $newConfig,
        ?User $user = null
    ): void {
        $changes = $this->detectConfigurationChanges($oldConfig, $newConfig);

        $this->logEmailOperation('smtp_configuration_changed', [
            'user_id' => $user?->id,
            'changes' => $changes,
            'configuration_id' => $newConfig['id'] ?? null,
        ], 'info', null, [
            'security_sensitive' => $this->hasSecuritySensitiveChanges($changes),
            'requires_testing' => true,
        ]);
    }

    /**
     * Log queue processing events
     */
    public function logQueueProcessing(
        string $jobClass,
        array $jobData,
        string $status,
        ?string $errorMessage = null,
        array $processingMetadata = []
    ): void {
        $this->logEmailOperation('queue_job_processed', [
            'job_class' => $jobClass,
            'job_data' => $jobData,
            'processing_status' => $status,
            'error_message' => $errorMessage,
        ], $status === 'failed' ? 'error' : 'info', $errorMessage,
            array_merge($processingMetadata, [
                'queue_name' => $processingMetadata['queue'] ?? 'default',
                'processing_time_ms' => $processingMetadata['processing_time'] ?? null,
                'memory_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ])
        );
    }

    /**
     * Log user preference changes
     */
    public function logUserPreferenceChange(
        User $user,
        string $preferenceType,
        array $oldPreferences,
        array $newPreferences
    ): void {
        $this->logEmailOperation('user_email_preferences_changed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'preference_type' => $preferenceType,
            'old_preferences' => $oldPreferences,
            'new_preferences' => $newPreferences,
        ], 'info', null, [
            'affects_notifications' => true,
            'preference_category' => $preferenceType,
        ]);
    }

    /**
     * Get comprehensive email statistics with detailed breakdowns
     */
    public function getDetailedEmailStatistics(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        array $filters = []
    ): array {
        $query = EmailLog::query();

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Apply additional filters
        if (isset($filters['sender'])) {
            $query->where('sender', $filters['sender']);
        }

        if (isset($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        // Get basic statistics
        $basicStats = EmailLog::getStatistics($startDate, $endDate);

        // Get detailed breakdowns
        $statusBreakdown = $query->clone()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $hourlyBreakdown = $query->clone()
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->pluck('count', 'hour')
            ->toArray();

        $dailyBreakdown = $query->clone()
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Calculate performance metrics
        $performanceMetrics = $this->calculatePerformanceMetrics($query->clone());

        // Get failure analysis
        $failureAnalysis = $this->analyzeFailures($query->clone());

        return array_merge($basicStats, [
            'detailed_breakdown' => [
                'by_status' => $statusBreakdown,
                'by_hour' => $hourlyBreakdown,
                'by_day' => $dailyBreakdown,
            ],
            'performance_metrics' => $performanceMetrics,
            'failure_analysis' => $failureAnalysis,
            'period' => [
                'start_date' => $startDate?->toISOString(),
                'end_date' => $endDate?->toISOString(),
                'duration_hours' => $startDate && $endDate ?
                    $endDate->diffInHours($startDate) : null,
            ],
        ]);
    }

    /**
     * Clean up old email logs based on retention policy
     */
    public function cleanupOldLogs(array $retentionPolicy = []): array
    {
        $defaultPolicy = [
            'successful_emails_days' => 90,
            'failed_emails_days' => 365,
            'batch_size' => 1000,
            'dry_run' => false,
        ];

        $policy = array_merge($defaultPolicy, $retentionPolicy);
        $results = [
            'successful_deleted' => 0,
            'failed_deleted' => 0,
            'errors' => [],
        ];

        try {
            // Clean up successful emails
            $successfulCutoff = now()->subDays($policy['successful_emails_days']);
            $successfulQuery = EmailLog::whereIn('status', [
                EmailLog::STATUS_SENT,
                EmailLog::STATUS_DELIVERED,
            ])->where('created_at', '<', $successfulCutoff);

            if (!$policy['dry_run']) {
                $results['successful_deleted'] = $successfulQuery->delete();
            } else {
                $results['successful_deleted'] = $successfulQuery->count();
            }

            // Clean up failed emails (keep longer for analysis)
            $failedCutoff = now()->subDays($policy['failed_emails_days']);
            $failedQuery = EmailLog::whereIn('status', [
                EmailLog::STATUS_FAILED,
                EmailLog::STATUS_BOUNCED,
                EmailLog::STATUS_REJECTED,
            ])->where('created_at', '<', $failedCutoff);

            if (!$policy['dry_run']) {
                $results['failed_deleted'] = $failedQuery->delete();
            } else {
                $results['failed_deleted'] = $failedQuery->count();
            }

            $this->logEmailOperation('log_cleanup_completed', [
                'policy' => $policy,
                'results' => $results,
            ], 'info', null, [
                'cleanup_type' => $policy['dry_run'] ? 'dry_run' : 'actual',
                'total_deleted' => $results['successful_deleted'] + $results['failed_deleted'],
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();

            $this->logEmailOperation('log_cleanup_failed', [
                'policy' => $policy,
                'error' => $e->getMessage(),
            ], 'error', $e->getMessage());
        }

        return $results;
    }

    /**
     * Get log level based on status
     */
    protected function getLogLevel(string $status): string
    {
        return match ($status) {
            'error', 'failed', 'critical' => 'error',
            'warning', 'bounced' => 'warning',
            'info', 'sent', 'delivered', 'success' => 'info',
            'debug' => 'debug',
            default => 'info',
        };
    }

    /**
     * Determine if operation should be stored in database
     */
    protected function shouldStoreInDatabase(string $operation): bool
    {
        $significantOperations = [
            'bulk_email_batch_created',
            'smtp_configuration_changed',
            'template_created',
            'template_updated',
            'template_deleted',
            'email_delivery_failed',
            'critical_system_error',
        ];

        return in_array($operation, $significantOperations);
    }

    /**
     * Store operation log in database
     */
    protected function storeOperationLog(string $operation, array $logData): void
    {
        try {
            DB::table('email_operation_logs')->insert([
                'operation' => $operation,
                'data' => json_encode($logData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Don't fail the main operation if logging fails
            Log::error('Failed to store operation log', [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Categorize failure type for analysis
     */
    protected function categorizeFailure(string $errorMessage): string
    {
        $errorMessage = strtolower($errorMessage);

        if (str_contains($errorMessage, 'connection') || str_contains($errorMessage, 'timeout')) {
            return 'connection_error';
        }

        if (str_contains($errorMessage, 'authentication') || str_contains($errorMessage, 'login')) {
            return 'authentication_error';
        }

        if (str_contains($errorMessage, 'invalid') || str_contains($errorMessage, 'malformed')) {
            return 'validation_error';
        }

        if (str_contains($errorMessage, 'bounce') || str_contains($errorMessage, 'rejected')) {
            return 'delivery_error';
        }

        if (str_contains($errorMessage, 'rate') || str_contains($errorMessage, 'limit')) {
            return 'rate_limit_error';
        }

        return 'unknown_error';
    }

    /**
     * Check if failure is critical and needs immediate attention
     */
    protected function isCriticalFailure(string $errorMessage): bool
    {
        $criticalPatterns = [
            'authentication failed',
            'connection refused',
            'server not found',
            'certificate error',
            'ssl error',
        ];

        $errorMessage = strtolower($errorMessage);

        foreach ($criticalPatterns as $pattern) {
            if (str_contains($errorMessage, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alert about critical email failures
     */
    protected function alertCriticalEmailFailure(EmailLog $emailLog, string $errorMessage): void
    {
        // Log critical alert
        Log::critical('Critical email delivery failure', [
            'email_log_id' => $emailLog->id,
            'recipient' => $emailLog->recipient,
            'subject' => $emailLog->subject,
            'error_message' => $errorMessage,
            'retry_count' => $emailLog->retry_count,
        ]);

        // TODO: Implement additional alerting mechanisms (Slack, email to admins, etc.)
        // This could be extended to send notifications to administrators
    }

    /**
     * Detect configuration changes
     */
    protected function detectConfigurationChanges(array $oldConfig, array $newConfig): array
    {
        $changes = [];
        $sensitiveFields = ['password', 'username', 'host', 'port', 'encryption'];

        foreach ($newConfig as $key => $value) {
            if (!isset($oldConfig[$key]) || $oldConfig[$key] !== $value) {
                $changes[$key] = [
                    'old' => $oldConfig[$key] ?? null,
                    'new' => in_array($key, $sensitiveFields) ? '[REDACTED]' : $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if changes involve security-sensitive fields
     */
    protected function hasSecuritySensitiveChanges(array $changes): bool
    {
        $sensitiveFields = ['password', 'username', 'host', 'port', 'encryption'];

        foreach ($sensitiveFields as $field) {
            if (isset($changes[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate performance metrics
     */
    protected function calculatePerformanceMetrics($query): array
    {
        $sentEmails = $query->clone()->whereNotNull('sent_at');

        $avgDeliveryTime = $sentEmails->clone()
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_seconds')
            ->value('avg_seconds');

        $maxDeliveryTime = $sentEmails->clone()
            ->selectRaw('MAX(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as max_seconds')
            ->value('max_seconds');

        $minDeliveryTime = $sentEmails->clone()
            ->selectRaw('MIN(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as min_seconds')
            ->value('min_seconds');

        return [
            'average_delivery_time_seconds' => $avgDeliveryTime ? round($avgDeliveryTime, 2) : null,
            'max_delivery_time_seconds' => $maxDeliveryTime,
            'min_delivery_time_seconds' => $minDeliveryTime,
            'emails_with_timing_data' => $sentEmails->count(),
        ];
    }

    /**
     * Analyze failure patterns
     */
    protected function analyzeFailures($query): array
    {
        $failedEmails = $query->clone()->where('status', EmailLog::STATUS_FAILED);

        $failuresByCategory = [];
        $topFailureReasons = [];

        $failures = $failedEmails->select('error_message')->get();

        foreach ($failures as $failure) {
            $category = $this->categorizeFailure($failure->error_message);
            $failuresByCategory[$category] = ($failuresByCategory[$category] ?? 0) + 1;

            $reason = substr($failure->error_message, 0, 100);
            $topFailureReasons[$reason] = ($topFailureReasons[$reason] ?? 0) + 1;
        }

        arsort($topFailureReasons);
        $topFailureReasons = array_slice($topFailureReasons, 0, 10, true);

        return [
            'total_failures' => $failedEmails->count(),
            'failures_by_category' => $failuresByCategory,
            'top_failure_reasons' => $topFailureReasons,
            'retry_analysis' => [
                'emails_with_retries' => $failedEmails->clone()->where('retry_count', '>', 0)->count(),
                'max_retry_count' => $failedEmails->max('retry_count') ?? 0,
                'avg_retry_count' => $failedEmails->avg('retry_count') ?? 0,
            ],
        ];
    }
}
