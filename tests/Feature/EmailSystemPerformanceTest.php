<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailSystemPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected EmailService $emailService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emailService = app(EmailService::class);

        // Create active email configuration for tests
        EmailConfiguration::factory()->create([
            'is_active' => true,
            'daily_limit' => 10000,
            'rate_limit' => 100
        ]);
    }

    /** @test */
    public function it_handles_large_bulk_email_processing_efficiently()
    {
        Bus::fake();

        // Generate large recipient list
        $recipients = [];
        for ($i = 0; $i < 1000; $i++) {
            $recipients[] = "user{$i}@example.com";
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Performance Test Subject',
            'Performance test content',
            null,
            [],
            null,
            [],
            50 // Chunk size
        );

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        // Performance assertions
        $this->assertLessThan(5.0, $executionTime, 'Bulk email processing should complete within 5 seconds');
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 'Memory usage should be less than 50MB');

        // Verify proper batching
        $this->assertEquals(1000, $result['total_recipients']);
        $this->assertEquals(20, $result['chunks']); // 1000 / 50 = 20 chunks

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 20;
        });
    }

    /** @test */
    public function it_processes_bulk_email_with_memory_efficiency()
    {
        Bus::fake();

        // Create large template with variables
        $template = EmailTemplate::factory()->create([
            'subject' => 'Welcome {{name}} to {{institution}}!',
            'html_content' => str_repeat('<p>Welcome {{name}} to {{institution}}! ' . str_repeat('Content ', 100) . '</p>', 10),
            'text_content' => str_repeat('Welcome {{name}} to {{institution}}! ' . str_repeat('Content ', 100), 10),
            'variables' => ['name', 'institution']
        ]);

        $recipients = [];
        for ($i = 0; $i < 500; $i++) {
            $recipients[] = "user{$i}@example.com";
        }

        $variables = [
            'name' => 'Test User',
            'institution' => 'Test University'
        ];

        $startMemory = memory_get_peak_usage();

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Original Subject',
            'Original Content',
            $template,
            [],
            null,
            $variables,
            25 // Smaller chunk size for memory efficiency
        );

        $peakMemory = memory_get_peak_usage();
        $memoryIncrease = $peakMemory - $startMemory;

        // Memory efficiency assertions
        $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 'Memory increase should be less than 100MB');
        $this->assertEquals(500, $result['total_recipients']);
        $this->assertEquals(20, $result['chunks']); // 500 / 25 = 20 chunks
    }

    /** @test */
    public function it_handles_concurrent_bulk_email_operations()
    {
        Bus::fake();

        $recipients1 = array_map(fn($i) => "batch1_user{$i}@example.com", range(1, 100));
        $recipients2 = array_map(fn($i) => "batch2_user{$i}@example.com", range(1, 100));
        $recipients3 = array_map(fn($i) => "batch3_user{$i}@example.com", range(1, 100));

        $startTime = microtime(true);

        // Simulate concurrent bulk email operations
        $result1 = $this->emailService->sendBulkEmail($recipients1, 'Batch 1', 'Content 1');
        $result2 = $this->emailService->sendBulkEmail($recipients2, 'Batch 2', 'Content 2');
        $result3 = $this->emailService->sendBulkEmail($recipients3, 'Batch 3', 'Content 3');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Performance assertions
        $this->assertLessThan(3.0, $executionTime, 'Concurrent operations should complete within 3 seconds');

        // Verify all batches were processed
        $this->assertEquals(100, $result1['total_recipients']);
        $this->assertEquals(100, $result2['total_recipients']);
        $this->assertEquals(100, $result3['total_recipients']);

        // Verify unique batch IDs
        $this->assertNotEquals($result1['batch_id'], $result2['batch_id']);
        $this->assertNotEquals($result2['batch_id'], $result3['batch_id']);
        $this->assertNotEquals($result1['batch_id'], $result3['batch_id']);

        Bus::assertBatchCount(3);
    }

    /** @test */
    public function it_efficiently_queries_email_statistics_for_large_datasets()
    {
        // Create large dataset of email logs
        $batchSize = 1000;
        $totalRecords = 5000;

        for ($batch = 0; $batch < $totalRecords / $batchSize; $batch++) {
            $logs = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $logs[] = [
                    'recipient' => "user{$batch}_{$i}@example.com",
                    'sender' => 'system@example.com',
                    'subject' => "Test Email {$batch}_{$i}",
                    'status' => collect([
                        EmailLog::STATUS_SENT,
                        EmailLog::STATUS_DELIVERED,
                        EmailLog::STATUS_FAILED
                    ])->random(),
                    'created_at' => now()->subDays(rand(0, 30)),
                    'updated_at' => now()->subDays(rand(0, 30)),
                ];
            }
            DB::table('email_logs')->insert($logs);
        }

        $startTime = microtime(true);
        $queryCount = DB::getQueryLog();
        DB::enableQueryLog();

        // Test statistics query performance
        $stats = $this->emailService->getEmailStatistics(
            now()->subMonth(),
            now()
        );

        $queries = DB::getQueryLog();
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Performance assertions
        $this->assertLessThan(2.0, $executionTime, 'Statistics query should complete within 2 seconds');
        $this->assertLessThan(10, count($queries), 'Should use efficient queries (less than 10 queries)');

        // Verify statistics accuracy
        $this->assertArrayHasKey('total_sent', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertGreaterThan(0, $stats['total_sent']);
    }

    /** @test */
    public function it_handles_template_rendering_performance_with_large_variable_sets()
    {
        $template = EmailTemplate::factory()->create([
            'subject' => 'Performance Test for {{name}} at {{institution}} - {{course_count}} courses',
            'html_content' => $this->generateLargeTemplateContent(),
            'text_content' => $this->generateLargeTextContent(),
            'variables' => ['name', 'institution', 'course_count', 'semester', 'year', 'gpa', 'credits']
        ]);

        $variables = [
            'name' => 'John Doe',
            'institution' => 'Test University',
            'course_count' => '15',
            'semester' => 'Fall',
            'year' => '2024',
            'gpa' => '3.85',
            'credits' => '120'
        ];

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Render template multiple times to test performance
        for ($i = 0; $i < 100; $i++) {
            $rendered = app(\App\Services\EmailTemplateService::class)
                ->renderTemplate($template->id, $variables);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;

        // Performance assertions
        $this->assertLessThan(1.0, $executionTime, 'Template rendering should complete within 1 second for 100 renders');
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Memory usage should be less than 10MB');
    }

    /** @test */
    public function it_efficiently_processes_email_queue_with_high_throughput()
    {
        Queue::fake();

        // Create multiple email configurations to test load balancing
        EmailConfiguration::factory()->count(3)->create([
            'is_active' => true,
            'daily_limit' => 1000,
            'rate_limit' => 50
        ]);

        $startTime = microtime(true);

        // Queue many individual emails
        for ($i = 0; $i < 500; $i++) {
            $this->emailService->sendSingleEmail(
                "user{$i}@example.com",
                "Test Email {$i}",
                "Test content for email {$i}"
            );
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Performance assertions
        $this->assertLessThan(5.0, $executionTime, 'Queueing 500 emails should complete within 5 seconds');

        // Verify all emails were queued
        Queue::assertPushed(\App\Jobs\SendSingleEmailJob::class, 500);

        // Verify email logs were created efficiently
        $this->assertEquals(500, EmailLog::count());
    }

    /** @test */
    public function it_handles_bulk_email_progress_tracking_efficiently()
    {
        // Create a large batch of email logs
        $batchId = 'performance-test-batch-' . uniqid();
        $totalEmails = 1000;

        $logs = [];
        for ($i = 0; $i < $totalEmails; $i++) {
            $logs[] = [
                'batch_id' => $batchId,
                'recipient' => "user{$i}@example.com",
                'sender' => 'system@example.com',
                'subject' => "Batch Email {$i}",
                'status' => collect([
                    EmailLog::STATUS_QUEUED,
                    EmailLog::STATUS_SENT,
                    EmailLog::STATUS_DELIVERED,
                    EmailLog::STATUS_FAILED
                ])->random(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('email_logs')->insert($logs);

        $startTime = microtime(true);

        // Test progress tracking performance
        $progress = $this->emailService->getBulkEmailProgress($batchId);

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Performance assertions
        $this->assertLessThan(0.5, $executionTime, 'Progress tracking should complete within 0.5 seconds');

        // Verify progress data accuracy
        $this->assertEquals($batchId, $progress['batch_id']);
        $this->assertEquals($totalEmails, $progress['total_emails']);
        $this->assertArrayHasKey('status_breakdown', $progress);
        $this->assertArrayHasKey('progress_percentage', $progress);
    }

    /** @test */
    public function it_optimizes_database_queries_for_email_operations()
    {
        DB::enableQueryLog();

        $recipients = array_map(fn($i) => "user{$i}@example.com", range(1, 50));

        // Clear query log
        DB::flushQueryLog();

        Bus::fake();
        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Query Optimization Test',
            'Test content'
        );

        $queries = DB::getQueryLog();

        // Query optimization assertions
        $this->assertLessThan(15, count($queries), 'Should use minimal database queries');

        // Verify no N+1 query problems
        $selectQueries = array_filter($queries, fn($query) => str_starts_with(strtolower($query['query']), 'select'));
        $this->assertLessThan(10, count($selectQueries), 'Should minimize SELECT queries');

        // Verify bulk operations use batch inserts
        $insertQueries = array_filter($queries, fn($query) => str_starts_with(strtolower($query['query']), 'insert'));
        $this->assertLessThan(5, count($insertQueries), 'Should use batch inserts');
    }

    /**
     * Generate large template content for performance testing
     */
    private function generateLargeTemplateContent(): string
    {
        $content = '<html><body>';
        $content .= '<h1>Welcome {{name}} to {{institution}}</h1>';
        $content .= '<p>You are enrolled in {{course_count}} courses for {{semester}} {{year}}.</p>';
        $content .= '<p>Your current GPA is {{gpa}} with {{credits}} total credits.</p>';

        // Add repetitive content to test performance
        for ($i = 0; $i < 50; $i++) {
            $content .= "<div>Section {$i}: This is test content for {{name}} at {{institution}}. ";
            $content .= "Semester: {{semester}} {{year}}, GPA: {{gpa}}, Credits: {{credits}}.</div>";
        }

        $content .= '</body></html>';
        return $content;
    }

    /**
     * Generate large text content for performance testing
     */
    private function generateLargeTextContent(): string
    {
        $content = "Welcome {{name}} to {{institution}}\n\n";
        $content .= "You are enrolled in {{course_count}} courses for {{semester}} {{year}}.\n";
        $content .= "Your current GPA is {{gpa}} with {{credits}} total credits.\n\n";

        // Add repetitive content to test performance
        for ($i = 0; $i < 50; $i++) {
            $content .= "Section {$i}: This is test content for {{name}} at {{institution}}. ";
            $content .= "Semester: {{semester}} {{year}}, GPA: {{gpa}}, Credits: {{credits}}.\n";
        }

        return $content;
    }
}
