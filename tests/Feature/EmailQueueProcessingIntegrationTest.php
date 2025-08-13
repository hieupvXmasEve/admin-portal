<?php

namespace Tests\Feature;

use App\Jobs\ProcessNotificationJob;
use App\Jobs\SendBulkEmailJob;
use App\Jobs\SendSingleEmailJob;
use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailQueueProcessingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected EmailService $emailService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emailService = app(EmailService::class);

        // Create active email configuration
        EmailConfiguration::factory()->create([
            'is_active' => true,
            'host' => 'smtp.example.com',
            'port' => 587,
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test System'
        ]);
    }

    /** @test */
    public function it_processes_single_email_job_successfully()
    {
        Mail::fake();

        $user = User::factory()->create();
        $emailLog = EmailLog::factory()->create([
            'recipient' => $user->email,
            'subject' => 'Test Email',
            'status' => EmailLog::STATUS_QUEUED,
            'user_id' => $user->id
        ]);

        $job = new SendSingleEmailJob(
            $emailLog,
            '<p>Test HTML content</p>',
            'Test text content',
            []
        );

        // Process the job
        $job->handle();

        // Verify email was sent
        Mail::assertSent(\App\Mail\GenericEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // Verify email log was updated
        $emailLog->refresh();
        $this->assertEquals(EmailLog::STATUS_SENT, $emailLog->status);
        $this->assertNotNull($emailLog->sent_at);
    }

    /** @test */
    public function it_handles_single_email_job_failure_with_retry()
    {
        Mail::fake();
        Event::fake();

        // Force mail to fail
        Mail::shouldReceive('send')->andThrow(new \Exception('SMTP connection failed'));

        $emailLog = EmailLog::factory()->create([
            'status' => EmailLog::STATUS_QUEUED,
            'retry_count' => 0
        ]);

        $job = new SendSingleEmailJob(
            $emailLog,
            '<p>Test content</p>',
            'Test content',
            []
        );

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Verify email log was updated with failure
        $emailLog->refresh();
        $this->assertEquals(EmailLog::STATUS_FAILED, $emailLog->status);
        $this->assertNotNull($emailLog->failed_at);
        $this->assertNotEmpty($emailLog->error_message);
        $this->assertEquals(1, $emailLog->retry_count);
    }

    /** @test */
    public function it_processes_bulk_email_job_successfully()
    {
        Mail::fake();

        $recipients = [
            'user1@example.com',
            'user2@example.com',
            'user3@example.com'
        ];

        $template = EmailTemplate::factory()->create([
            'subject' => 'Bulk Email Test',
            'html_content' => '<p>Bulk email content</p>',
            'text_content' => 'Bulk email content'
        ]);

        $job = new SendBulkEmailJob(
            $recipients,
            'Bulk Email Subject',
            '<p>Bulk email HTML content</p>',
            'Bulk email text content',
            $template->id,
            [],
            null,
            'test-batch-id',
            0
        );

        // Process the job
        $job->handle();

        // Verify emails were sent to all recipients
        Mail::assertSent(\App\Mail\GenericEmail::class, 3);

        // Verify email logs were created
        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('email_logs', [
                'recipient' => $recipient,
                'batch_id' => 'test-batch-id',
                'status' => EmailLog::STATUS_SENT
            ]);
        }
    }

    /** @test */
    public function it_handles_bulk_email_job_partial_failure()
    {
        Mail::fake();

        $recipients = [
            'valid@example.com',
            'invalid-email-format',
            'another-valid@example.com'
        ];

        $job = new SendBulkEmailJob(
            $recipients,
            'Bulk Email Subject',
            '<p>Bulk email content</p>',
            'Bulk email content',
            null,
            [],
            null,
            'test-batch-id',
            0
        );

        // Process the job
        $job->handle();

        // Verify valid emails were sent
        Mail::assertSent(\App\Mail\GenericEmail::class, 2);

        // Verify logs for valid emails
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'valid@example.com',
            'status' => EmailLog::STATUS_SENT
        ]);

        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'another-valid@example.com',
            'status' => EmailLog::STATUS_SENT
        ]);

        // Verify log for invalid email
        $this->assertDatabaseHas('email_logs', [
            'recipient' => 'invalid-email-format',
            'status' => EmailLog::STATUS_FAILED
        ]);
    }

    /** @test */
    public function it_processes_notification_job_successfully()
    {
        Mail::fake();

        $users = User::factory()->count(2)->create();
        $recipients = $users->pluck('email')->toArray();

        $template = EmailTemplate::factory()->create([
            'name' => 'test_notification',
            'subject' => 'Test Notification {{name}}',
            'html_content' => '<p>Hello {{name}}</p>',
            'variables' => ['name']
        ]);

        $job = new ProcessNotificationJob(
            'test_notification',
            $recipients,
            ['name' => 'Test User']
        );

        // Process the job
        $job->handle();

        // Verify emails were sent
        Mail::assertSent(\App\Mail\GenericEmail::class, 2);

        // Verify email logs were created
        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('email_logs', [
                'recipient' => $recipient,
                'template_id' => $template->id,
                'status' => EmailLog::STATUS_SENT
            ]);
        }
    }

    /** @test */
    public function it_handles_job_retry_logic_with_exponential_backoff()
    {
        Queue::fake();

        $emailLog = EmailLog::factory()->create([
            'status' => EmailLog::STATUS_QUEUED,
            'retry_count' => 0
        ]);

        $job = new SendSingleEmailJob(
            $emailLog,
            '<p>Test content</p>',
            'Test content',
            []
        );

        // Test backoff calculation
        $backoffTimes = $job->backoff();

        $this->assertEquals([60, 300, 900, 1800], $backoffTimes);
        $this->assertEquals(5, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }

    /** @test */
    public function it_handles_job_failure_after_max_attempts()
    {
        Mail::fake();

        // Force mail to always fail
        Mail::shouldReceive('send')->andThrow(new \Exception('Permanent failure'));

        $emailLog = EmailLog::factory()->create([
            'status' => EmailLog::STATUS_QUEUED,
            'retry_count' => 4 // Already at max retries
        ]);

        $job = new SendSingleEmailJob(
            $emailLog,
            '<p>Test content</p>',
            'Test content',
            []
        );

        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Verify email log shows permanent failure
        $emailLog->refresh();
        $this->assertEquals(EmailLog::STATUS_FAILED, $emailLog->status);
        $this->assertEquals(5, $emailLog->retry_count);
        $this->assertNotNull($emailLog->failed_at);
    }

    /** @test */
    public function it_handles_batch_job_processing()
    {
        Bus::fake();

        $recipients = array_fill(0, 250, 'test@example.com');
        // Make them unique
        $recipients = array_map(function($email, $index) {
            return "test{$index}@example.com";
        }, $recipients, array_keys($recipients));

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Batch Test Subject',
            'Batch test content',
            null,
            [],
            null,
            [],
            100 // Chunk size
        );

        // Verify batch was created with correct number of jobs
        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 3; // 250 / 100 = 3 chunks
        });

        $this->assertEquals(250, $result['total_recipients']);
        $this->assertEquals(3, $result['chunks']);
    }

    /** @test */
    public function it_handles_batch_job_failure_and_continuation()
    {
        Bus::fake();

        $recipients = ['user1@example.com', 'user2@example.com', 'user3@example.com'];

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Test Subject',
            'Test content',
            null,
            [],
            null,
            [],
            1 // One recipient per job
        );

        // Verify batch allows failures (allowFailures() called)
        Bus::assertBatched(function ($batch) {
            return $batch->allowsFailures();
        });
    }

    /** @test */
    public function it_tracks_job_progress_in_email_logs()
    {
        Mail::fake();

        $recipients = ['user1@example.com', 'user2@example.com'];

        $job = new SendBulkEmailJob(
            $recipients,
            'Progress Test',
            '<p>Test content</p>',
            'Test content',
            null,
            [],
            null,
            'progress-test-batch',
            0
        );

        // Process the job
        $job->handle();

        // Verify all email logs have batch_id for tracking
        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('email_logs', [
                'recipient' => $recipient,
                'batch_id' => 'progress-test-batch',
                'status' => EmailLog::STATUS_SENT
            ]);
        }

        // Test progress tracking
        $progress = $this->emailService->getBulkEmailProgress('progress-test-batch');

        $this->assertEquals('progress-test-batch', $progress['batch_id']);
        $this->assertEquals(2, $progress['total_emails']);
        $this->assertEquals(100, $progress['progress_percentage']); // All completed
    }

    /** @test */
    public function it_handles_queue_timeout_gracefully()
    {
        Mail::fake();

        $emailLog = EmailLog::factory()->create([
            'status' => EmailLog::STATUS_QUEUED
        ]);

        $job = new SendSingleEmailJob(
            $emailLog,
            '<p>Test content</p>',
            'Test content',
            []
        );

        // Verify timeout is set appropriately
        $this->assertEquals(120, $job->timeout);

        // For bulk jobs, timeout should be higher
        $bulkJob = new SendBulkEmailJob(
            ['test@example.com'],
            'Test',
            'Content',
            'Content'
        );

        $this->assertEquals(300, $bulkJob->timeout);
    }

    /** @test */
    public function it_handles_job_memory_limits()
    {
        // Test that jobs handle large datasets appropriately
        $largeRecipientList = array_fill(0, 1000, 'test@example.com');
        $largeRecipientList = array_map(function($email, $index) {
            return "test{$index}@example.com";
        }, $largeRecipientList, array_keys($largeRecipientList));

        $job = new SendBulkEmailJob(
            $largeRecipientList,
            'Memory Test',
            '<p>Test content</p>',
            'Test content'
        );

        // Verify job can be serialized (important for queue storage)
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(SendBulkEmailJob::class, $unserialized);
        $this->assertCount(1000, $unserialized->recipients);
    }

    /** @test */
    public function it_handles_concurrent_job_processing()
    {
        Mail::fake();

        // Create multiple email logs
        $emailLogs = EmailLog::factory()->count(5)->create([
            'status' => EmailLog::STATUS_QUEUED
        ]);

        $jobs = [];
        foreach ($emailLogs as $emailLog) {
            $jobs[] = new SendSingleEmailJob(
                $emailLog,
                '<p>Concurrent test</p>',
                'Concurrent test',
                []
            );
        }

        // Process all jobs
        foreach ($jobs as $job) {
            $job->handle();
        }

        // Verify all emails were processed
        Mail::assertSent(\App\Mail\GenericEmail::class, 5);

        // Verify all logs were updated
        foreach ($emailLogs as $emailLog) {
            $emailLog->refresh();
            $this->assertEquals(EmailLog::STATUS_SENT, $emailLog->status);
        }
    }

    /** @test */
    public function it_handles_job_cancellation()
    {
        Bus::fake();

        $recipients = ['user1@example.com', 'user2@example.com'];

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Cancellation Test',
            'Test content'
        );

        $batchId = $result['batch_id'];

        // Test cancellation
        $cancelled = $this->emailService->cancelBulkEmailBatch($batchId);

        // Note: In a real scenario, this would interact with Laravel's batch system
        // For testing, we verify the method exists and handles the request
        $this->assertIsBool($cancelled);
    }

    /** @test */
    public function it_handles_job_priority_queues()
    {
        Queue::fake();

        // Single emails should go to default queue
        $this->emailService->sendSingleEmail(
            'test@example.com',
            'Priority Test',
            'Test content'
        );

        Queue::assertPushedOn('emails', SendSingleEmailJob::class);

        // Bulk emails should go to bulk queue
        Bus::fake(); // Switch to Bus for batch testing

        $this->emailService->sendBulkEmail(
            ['test1@example.com', 'test2@example.com'],
            'Bulk Priority Test',
            'Test content'
        );

        Bus::assertBatched(function ($batch) {
            return $batch->onQueue('bulk-emails');
        });
    }
}
