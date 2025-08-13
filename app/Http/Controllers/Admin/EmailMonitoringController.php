<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EmailLoggingService;
use App\Services\EmailService;
use App\Models\EmailLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmailMonitoringController extends Controller
{
    protected EmailLoggingService $loggingService;
    protected EmailService $emailService;

    public function __construct(EmailLoggingService $loggingService, EmailService $emailService)
    {
        $this->loggingService = $loggingService;
        $this->emailService = $emailService;
    }

    /**
     * Display the email monitoring dashboard
     */
    public function index(): Response
    {
        return Inertia::render('Admin/EmailMonitoring/Dashboard', [
            'initialData' => $this->getDashboardData(),
        ]);
    }

    /**
     * Get dashboard data for initial load
     */
    protected function getDashboardData(): array
    {
        $now = now();
        $last24Hours = $now->copy()->subDay();
        $lastWeek = $now->copy()->subWeek();

        return [
            'overview' => [
                'last_24_hours' => $this->loggingService->getDetailedEmailStatistics($last24Hours, $now),
                'last_week' => $this->loggingService->getDetailedEmailStatistics($lastWeek, $now),
            ],
            'queue_status' => $this->emailService->getQueueStatus(),
            'recent_failures' => $this->getRecentFailures(),
            'system_alerts' => $this->getSystemAlerts(),
        ];
    }

    /**
     * Get real-time email statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'filters' => 'nullable|array',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDay();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();
        $filters = $request->filters ?? [];

        $statistics = $this->loggingService->getDetailedEmailStatistics($startDate, $endDate, $filters);

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'meta' => [
                'generated_at' => now()->toISOString(),
                'period' => [
                    'start' => $startDate->toISOString(),
                    'end' => $endDate->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Get real-time queue status
     */
    public function getQueueStatus(): JsonResponse
    {
        $queueStatus = $this->emailService->getQueueStatus();

        return response()->json([
            'success' => true,
            'data' => $queueStatus,
        ]);
    }

    /**
     * Get email logs with filtering and pagination
     */
    public function getLogs(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:' . implode(',', array_keys(EmailLog::getStatuses())),
            'recipient' => 'nullable|string|max:255',
            'batch_id' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $filters = $request->only(['status', 'recipient', 'batch_id', 'start_date', 'end_date', 'per_page']);
        $logs = $this->emailService->getEmailLogs($filters);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ]);
    }

    /**
     * Get bulk email batch progress
     */
    public function getBatchProgress(string $batchId): JsonResponse
    {
        try {
            $progress = $this->emailService->getBulkEmailProgress($batchId);

            return response()->json([
                'success' => true,
                'data' => $progress,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Cancel bulk email batch
     */
    public function cancelBatch(string $batchId): JsonResponse
    {
        try {
            $cancelled = $this->emailService->cancelBulkEmailBatch($batchId);

            return response()->json([
                'success' => true,
                'data' => [
                    'cancelled' => $cancelled,
                    'message' => $cancelled ? 'Batch cancelled successfully' : 'Batch could not be cancelled (may already be completed)',
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get email delivery trends
     */
    public function getDeliveryTrends(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|string|in:hour,day,week,month',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $period = $request->period;
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subDays(7);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

        $trends = $this->calculateDeliveryTrends($period, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get failure analysis report
     */
    public function getFailureAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->subWeek();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

        $analysis = $this->generateFailureAnalysis($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Test email system health
     */
    public function testSystemHealth(): JsonResponse
    {
        $healthChecks = [
            'smtp_connection' => $this->testSmtpConnection(),
            'queue_workers' => $this->testQueueWorkers(),
            'database_connection' => $this->testDatabaseConnection(),
            'email_templates' => $this->testEmailTemplates(),
        ];

        $overallHealth = $this->calculateOverallHealth($healthChecks);

        return response()->json([
            'success' => true,
            'data' => [
                'overall_status' => $overallHealth,
                'checks' => $healthChecks,
                'tested_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Export email statistics
     */
    public function exportStatistics(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'required|string|in:csv,xlsx,json',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'filters' => 'nullable|array',
        ]);

        // TODO: Implement export functionality
        // This would generate and return a downloadable file with email statistics

        return response()->json([
            'success' => false,
            'message' => 'Export functionality not yet implemented',
        ], 501);
    }

    /**
     * Get recent email failures for dashboard
     */
    protected function getRecentFailures(int $limit = 10): array
    {
        return EmailLog::failed()
            ->with(['template:id,name', 'user:id,name,email'])
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'recipient' => $log->recipient,
                    'subject' => $log->subject,
                    'error_message' => $log->error_message,
                    'failed_at' => $log->failed_at?->toISOString(),
                    'retry_count' => $log->retry_count,
                    'template_name' => $log->template?->name,
                    'sender_name' => $log->user?->name,
                ];
            })
            ->toArray();
    }

    /**
     * Get system alerts based on current conditions
     */
    protected function getSystemAlerts(): array
    {
        $alerts = [];

        // Check for high failure rate
        $recentStats = $this->loggingService->getDetailedEmailStatistics(now()->subHour(), now());
        if ($recentStats['success_rate'] < 90 && $recentStats['total'] > 10) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'High Email Failure Rate',
                'message' => "Email success rate in the last hour is {$recentStats['success_rate']}%",
                'action' => 'Check SMTP configuration and recent error logs',
            ];
        }

        // Check for queue backlog
        $queueStatus = $this->emailService->getQueueStatus();
        foreach ($queueStatus['queue_sizes'] as $queue => $size) {
            if (is_numeric($size) && $size > 1000) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Queue Backlog',
                    'message' => "Queue '{$queue}' has {$size} pending jobs",
                    'action' => 'Consider scaling queue workers or investigating processing delays',
                ];
            }
        }

        // Check for failed jobs
        if ($queueStatus['failed_jobs_count'] > 100) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'High Failed Jobs Count',
                'message' => "There are {$queueStatus['failed_jobs_count']} failed jobs",
                'action' => 'Review failed jobs and retry or clear them',
            ];
        }

        return $alerts;
    }

    /**
     * Calculate delivery trends for charting
     */
    protected function calculateDeliveryTrends(string $period, Carbon $startDate, Carbon $endDate): array
    {
        $dateFormat = match ($period) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
        };

        $query = EmailLog::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period")
            ->selectRaw('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('period', 'status')
            ->orderBy('period');

        $results = $query->get()->groupBy('period');

        $trends = [];
        foreach ($results as $period => $statusCounts) {
            $periodData = [
                'period' => $period,
                'total' => $statusCounts->sum('count'),
                'sent' => $statusCounts->where('status', EmailLog::STATUS_SENT)->sum('count'),
                'delivered' => $statusCounts->where('status', EmailLog::STATUS_DELIVERED)->sum('count'),
                'failed' => $statusCounts->where('status', EmailLog::STATUS_FAILED)->sum('count'),
                'bounced' => $statusCounts->where('status', EmailLog::STATUS_BOUNCED)->sum('count'),
            ];

            $periodData['success_rate'] = $periodData['total'] > 0
                ? round((($periodData['sent'] + $periodData['delivered']) / $periodData['total']) * 100, 2)
                : 0;

            $trends[] = $periodData;
        }

        return $trends;
    }

    /**
     * Generate failure analysis report
     */
    protected function generateFailureAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $failedEmails = EmailLog::failed()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $analysis = [
            'total_failures' => $failedEmails->count(),
            'failure_categories' => [],
            'top_error_messages' => [],
            'failure_by_template' => [],
            'retry_analysis' => [
                'emails_with_retries' => $failedEmails->where('retry_count', '>', 0)->count(),
                'max_retries' => $failedEmails->max('retry_count') ?? 0,
                'avg_retries' => round($failedEmails->avg('retry_count') ?? 0, 2),
            ],
        ];

        // Categorize failures
        $categories = [];
        $errorMessages = [];

        foreach ($failedEmails as $email) {
            // Categorize error
            $category = $this->categorizeError($email->error_message);
            $categories[$category] = ($categories[$category] ?? 0) + 1;

            // Count error messages
            $shortError = substr($email->error_message, 0, 100);
            $errorMessages[$shortError] = ($errorMessages[$shortError] ?? 0) + 1;
        }

        arsort($categories);
        arsort($errorMessages);

        $analysis['failure_categories'] = $categories;
        $analysis['top_error_messages'] = array_slice($errorMessages, 0, 10, true);

        // Failure by template
        $templateFailures = $failedEmails->whereNotNull('template_id')
            ->groupBy('template_id')
            ->map->count()
            ->sortDesc()
            ->take(10);

        $analysis['failure_by_template'] = $templateFailures->toArray();

        return $analysis;
    }

    /**
     * Categorize error message for analysis
     */
    protected function categorizeError(string $errorMessage): string
    {
        $errorMessage = strtolower($errorMessage);

        if (str_contains($errorMessage, 'connection') || str_contains($errorMessage, 'timeout')) {
            return 'Connection Issues';
        }

        if (str_contains($errorMessage, 'authentication') || str_contains($errorMessage, 'login')) {
            return 'Authentication Errors';
        }

        if (str_contains($errorMessage, 'invalid') || str_contains($errorMessage, 'malformed')) {
            return 'Validation Errors';
        }

        if (str_contains($errorMessage, 'bounce') || str_contains($errorMessage, 'rejected')) {
            return 'Delivery Errors';
        }

        if (str_contains($errorMessage, 'rate') || str_contains($errorMessage, 'limit')) {
            return 'Rate Limiting';
        }

        return 'Other';
    }

    /**
     * Test SMTP connection health
     */
    protected function testSmtpConnection(): array
    {
        try {
            $config = $this->emailService->getActiveConfiguration();
            if (!$config) {
                return [
                    'status' => 'error',
                    'message' => 'No active SMTP configuration found',
                ];
            }

            $result = $this->emailService->testSmtpConnection($config);
            return [
                'status' => $result['success'] ? 'healthy' : 'error',
                'message' => $result['message'],
                'details' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'SMTP connection test failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test queue workers health
     */
    protected function testQueueWorkers(): array
    {
        // This is a simplified check - in production you might want to check actual worker processes
        $queueStatus = $this->emailService->getQueueStatus();

        $hasBacklog = false;
        foreach ($queueStatus['queue_sizes'] as $size) {
            if (is_numeric($size) && $size > 500) {
                $hasBacklog = true;
                break;
            }
        }

        return [
            'status' => $hasBacklog ? 'warning' : 'healthy',
            'message' => $hasBacklog ? 'Queue backlog detected' : 'Queue processing normally',
            'details' => $queueStatus['queue_sizes'],
        ];
    }

    /**
     * Test database connection health
     */
    protected function testDatabaseConnection(): array
    {
        try {
            EmailLog::count();
            return [
                'status' => 'healthy',
                'message' => 'Database connection is working',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Test email templates health
     */
    protected function testEmailTemplates(): array
    {
        try {
            $activeTemplates = \App\Models\EmailTemplate::where('is_active', true)->count();
            return [
                'status' => 'healthy',
                'message' => "Found {$activeTemplates} active email templates",
                'details' => ['active_templates' => $activeTemplates],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Template check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate overall system health
     */
    protected function calculateOverallHealth(array $healthChecks): string
    {
        $statuses = array_column($healthChecks, 'status');

        if (in_array('error', $statuses)) {
            return 'error';
        }

        if (in_array('warning', $statuses)) {
            return 'warning';
        }

        return 'healthy';
    }
}
