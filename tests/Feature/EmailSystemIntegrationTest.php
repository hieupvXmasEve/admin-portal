<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Jobs\SendSingleEmailJob;
use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\UserEmailPreference;
use App\Services\EmailService;
use App\Services\EmailTemplateService;
use App\Services\SmtpConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected EmailService $emailService;
    protected EmailTemplateService $templateService;
    protected SmtpConfigurationService $configService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailService = app(EmailService::class);
        $this->templateService = app(EmailTemplateService::class);
        $this->configService = app(SmtpConfigurationService::class);
    }

    /** @test */
    public function it_sends_single_email_end_to_end()
    {
        Queue::fake();

        // Create email configuration
        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'host' => 'smtp.example.com',
            'port' => 587,
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test System'
        ]);

        // Send email
        $emailLog = $this->emailService->sendSingleEmail(
            'recipient@example.com',
            'Test Subject',
            'Test email content'
        );

        // Verify email log was created
        $this->assertInstanceOf(EmailLog::class, $emailLog);
        $this->assertEquals('recipient@example.com', $emailLog->recipient);
        $this->assertEquals('Test Subject', $emailLog->subject);
        $this->assertEquals(EmailLog::STATUS_QUEUED, $emailLog->status);

        // Verify job was queued
        Queue::assertPushed(SendSingleEmailJob::class, function ($job) use ($emailLog) {
            return $job->emailLog->id === $emailLog->id;
        });
    }

    /** @test */
    public function it_sends_email_with_template_integration()
    {
        Queue::fake();

        // Create email template
        $template = EmailTemplate::factory()->create([
            'name' => 'welcome_email',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Welcome {{name}}!',
            'html_content' => '<h1>Welcome {{name}}!</h1><p>Your email is {{email}}</p>',
            'text_content' => 'Welcome {{name}}! Your email is {{email}}',
            'variables' => ['name', 'email'],
            'is_active' => true
        ]);

        // Send email with template
        $emailLog = $this->emailService->sendSingleEmail(
            'john@example.com',
            'Test Subject',
            'Fallback content',
            $template,
            [],
            null,
            ['name' => 'John Doe', 'email' => 'john@example.com']
        );

        // Verify template was used
        $this->assertEquals($template->id, $emailLog->template_id);

        Queue::assertPushed(SendSingleEmailJob::class);
    }

    /** @test */
    public function it_respects_user_email_preferences()
    {
        $user = User::factory()->create(['email' => 'user@example.com']);

        // Create preference that disables notifications
        UserEmailPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => 'general',
            'is_enabled' => false,
            'frequency' => UserEmailPreference::FREQUENCY_NEVER
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User has opted out of email notifications');

        $this->emailService->sendSingleEmail(
            'user@example.com',
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_sends_bulk_email_with_batching()
    {
        Bus::fake();

        $recipients = [
            'user1@example.com',
            'user2@example.com',
            'user3@example.com',
            'user4@example.com',
            'user5@example.com'
        ];

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Bulk Email Subject',
            'Bulk email content',
            null,
            [],
            null,
            [],
            2 // Chunk size of 2
        );

        // Verify batch was created
        $this->assertEquals(5, $result['total_recipients']);
        $this->assertEquals(3, $result['chunks']); // 5 recipients / 2 chunk size = 3 chunks
        $this->assertArrayHasKey('batch_id', $result);
        $this->assertArrayHasKey('laravel_batch_id', $result);

        // Verify batch jobs were dispatched
        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 3;
        });
    }

    /** @test */
    public function it_filters_invalid_emails_in_bulk_send()
    {
        Bus::fake();

        $recipients = [
            'valid1@example.com',
            'invalid-email',
            'valid2@example.com',
            'another-invalid',
            'valid3@example.com'
        ];

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Bulk Email Subject',
            'Bulk email content'
        );

        $this->assertEquals(3, $result['total_recipients']); // Only valid emails
        $this->assertEquals(2, $result['invalid_recipients']);
        $this->assertCount(2, $result['invalid_emails']);
    }

    /** @test */
    public function it_handles_template_rendering_in_bulk_email()
    {
        Bus::fake();

        $template = EmailTemplate::factory()->create([
            'subject' => 'Hello {{name}}!',
            'html_content' => '<p>Welcome {{name}}</p>',
            'variables' => ['name'],
            'is_active' => true
        ]);

        $recipients = ['user1@example.com', 'user2@example.com'];

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Original Subject',
            'Original Content',
            $template,
            [],
            null,
            ['name' => 'Test User']
        );

        $this->assertEquals(2, $result['total_recipients']);

        Bus::assertBatched(function ($batch) use ($template) {
            $job = $batch->jobs->first();
            return $job->templateId === $template->id;
        });
    }

    /** @test */
    public function it_tracks_bulk_email_progress()
    {
        Bus::fake();

        $recipients = ['user1@example.com', 'user2@example.com'];

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Test Subject',
            'Test Content'
        );

        $batchId = $result['batch_id'];

        // Create some email logs to simulate progress
        EmailLog::factory()->create([
            'batch_id' => $batchId,
            'status' => EmailLog::STATUS_SENT,
            'recipient' => 'user1@example.com'
        ]);

        EmailLog::factory()->create([
            'batch_id' => $batchId,
            'status' => EmailLog::STATUS_QUEUED,
            'recipient' => 'user2@example.com'
        ]);

        $progress = $this->emailService->getBulkEmailProgress($batchId);

        $this->assertEquals($batchId, $progress['batch_id']);
        $this->assertEquals(2, $progress['total_emails']);
        $this->assertArrayHasKey('status_breakdown', $progress);
        $this->assertArrayHasKey('progress_percentage', $progress);
    }

    /** @test */
    public function it_validates_smtp_configuration_integration()
    {
        $configData = [
            'name' => 'Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'test@example.com',
            'password' => 'password123',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test System',
            'daily_limit' => 1000,
            'rate_limit' => 50,
            'is_active' => true
        ];

        $config = $this->configService->create($configData);

        // Test the configuration
        $testResult = $this->configService->testConnection($config);

        $this->assertArrayHasKey('success', $testResult);
        $this->assertArrayHasKey('message', $testResult);

        // Verify configuration was updated with test result
        $config->refresh();
        $this->assertNotNull($config->last_tested_at);
        $this->assertNotNull($config->test_result);
    }

    /** @test */
    public function it_creates_and_renders_template_integration()
    {
        $templateData = [
            'name' => 'integration_test_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Welcome {{student_name}} to {{institution}}!',
            'html_content' => '<h1>Welcome {{student_name}}!</h1><p>You are now enrolled at {{institution}}.</p>',
            'text_content' => 'Welcome {{student_name}}! You are now enrolled at {{institution}}.',
            'description' => 'Integration test template'
        ];

        $template = $this->templateService->createTemplate($templateData);

        // Verify template was created with extracted variables
        $this->assertEquals(['student_name', 'institution'], $template->variables);

        // Render the template
        $variables = [
            'student_name' => 'John Doe',
            'institution' => 'Test University'
        ];

        $rendered = $this->templateService->renderTemplate($template->id, $variables);

        $this->assertEquals('Welcome John Doe to Test University!', $rendered['subject']);
        $this->assertStringContainsString('Welcome John Doe!', $rendered['html']);
        $this->assertStringContainsString('Test University', $rendered['html']);
        $this->assertStringContainsString('Welcome John Doe!', $rendered['text']);
    }

    /** @test */
    public function it_handles_template_versioning_integration()
    {
        // Create original template
        $originalTemplate = $this->templateService->createTemplate([
            'name' => 'versioned_template',
            'type' => EmailTemplate::TYPE_WELCOME,
            'subject' => 'Original Subject',
            'html_content' => '<p>Original content</p>',
        ]);

        $this->assertEquals(1, $originalTemplate->version);
        $this->assertTrue($originalTemplate->is_active);

        // Create new version
        $newVersion = $this->templateService->createNewVersion($originalTemplate->id, [
            'subject' => 'Updated Subject {{name}}',
            'html_content' => '<p>Updated content for {{name}}</p>',
        ]);

        $this->assertEquals(2, $newVersion->version);
        $this->assertEquals($originalTemplate->id, $newVersion->parent_id);
        $this->assertTrue($newVersion->is_active);

        // Verify original is deactivated
        $originalTemplate->refresh();
        $this->assertFalse($originalTemplate->is_active);

        // Verify rendering by name gets latest version
        $rendered = $this->templateService->renderTemplateByName('versioned_template', ['name' => 'Test']);
        $this->assertEquals('Updated Subject Test', $rendered['subject']);
    }

    /** @test */
    public function it_manages_user_preferences_integration()
    {
        $user = User::factory()->create();
        $preferenceService = app(\App\Services\UserEmailPreferenceService::class);

        // Initialize default preferences
        $preferences = $preferenceService->initializeDefaultPreferences($user->id);
        $this->assertGreaterThan(0, $preferences->count());

        // Update a specific preference
        $updatedPreference = $preferenceService->updateUserPreference(
            $user->id,
            UserEmailPreference::TYPE_WELCOME,
            false,
            UserEmailPreference::FREQUENCY_NEVER
        );

        $this->assertFalse($updatedPreference->is_enabled);
        $this->assertEquals(UserEmailPreference::FREQUENCY_NEVER, $updatedPreference->frequency);

        // Test email sending respects preferences
        $this->expectException(\InvalidArgumentException::class);

        $this->emailService->sendSingleEmail(
            $user->email,
            'Test Subject',
            'Test Content',
            EmailTemplate::factory()->create(['type' => EmailTemplate::TYPE_WELCOME])
        );
    }

    /** @test */
    public function it_handles_email_logging_integration()
    {
        Queue::fake();

        $user = User::factory()->create();
        $template = EmailTemplate::factory()->create();

        $emailLog = $this->emailService->sendSingleEmail(
            $user->email,
            'Test Subject',
            'Test Content',
            $template,
            [],
            $user
        );

        // Verify comprehensive logging
        $this->assertEquals($user->email, $emailLog->recipient);
        $this->assertEquals('Test Subject', $emailLog->subject);
        $this->assertEquals($template->id, $emailLog->template_id);
        $this->assertEquals($user->id, $emailLog->user_id);
        $this->assertEquals(EmailLog::STATUS_QUEUED, $emailLog->status);
        $this->assertNotNull($emailLog->queued_at);
        $this->assertIsArray($emailLog->metadata);
    }

    /** @test */
    public function it_handles_attachment_validation_integration()
    {
        // Create temporary test files
        $validFile = tempnam(sys_get_temp_dir(), 'test_attachment_');
        file_put_contents($validFile, 'Test file content');

        $nonExistentFile = '/path/to/nonexistent/file.pdf';

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Attachment file not found');

            $this->emailService->sendSingleEmail(
                'test@example.com',
                'Test Subject',
                'Test Content',
                null,
                [$validFile, $nonExistentFile] // One valid, one invalid
            );
        } finally {
            // Clean up
            if (file_exists($validFile)) {
                unlink($validFile);
            }
        }
    }

    /** @test */
    public function it_handles_daily_limit_validation_in_bulk_email()
    {
        // Create configuration with low daily limit
        EmailConfiguration::factory()->create([
            'is_active' => true,
            'daily_limit' => 5
        ]);

        // Create some existing email logs for today
        EmailLog::factory()->count(3)->create([
            'status' => EmailLog::STATUS_SENT,
            'created_at' => now()
        ]);

        $recipients = array_fill(0, 5, 'test@example.com');
        // Make them unique
        $recipients = array_map(function($email, $index) {
            return "test{$index}@example.com";
        }, $recipients, array_keys($recipients));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bulk email would exceed daily limit');

        $this->emailService->sendBulkEmail(
            $recipients,
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_gets_email_statistics_integration()
    {
        // Create various email logs
        EmailLog::factory()->create([
            'status' => EmailLog::STATUS_SENT,
            'created_at' => now()->subHour()
        ]);

        EmailLog::factory()->create([
            'status' => EmailLog::STATUS_DELIVERED,
            'created_at' => now()->subMinutes(30)
        ]);

        EmailLog::factory()->create([
            'status' => EmailLog::STATUS_FAILED,
            'created_at' => now()->subMinutes(15)
        ]);

        $stats = $this->emailService->getEmailStatistics(
            now()->subDay(),
            now()
        );

        $this->assertArrayHasKey('total_sent', $stats);
        $this->assertArrayHasKey('total_delivered', $stats);
        $this->assertArrayHasKey('total_failed', $stats);
        $this->assertArrayHasKey('success_rate', $stats);

        $this->assertEquals(3, $stats['total_sent']);
        $this->assertEquals(1, $stats['total_delivered']);
        $this->assertEquals(1, $stats['total_failed']);
    }

    /** @test */
    public function it_handles_queue_monitoring_integration()
    {
        $queueStatus = $this->emailService->getQueueStatus();

        $this->assertArrayHasKey('queue_sizes', $queueStatus);
        $this->assertArrayHasKey('failed_jobs_count', $queueStatus);
        $this->assertArrayHasKey('recent_statistics', $queueStatus);
        $this->assertArrayHasKey('active_batches', $queueStatus);
        $this->assertArrayHasKey('system_health', $queueStatus);
        $this->assertArrayHasKey('last_updated', $queueStatus);

        // Verify queue sizes structure
        $this->assertArrayHasKey('emails', $queueStatus['queue_sizes']);
        $this->assertArrayHasKey('bulk-emails', $queueStatus['queue_sizes']);
        $this->assertArrayHasKey('notifications', $queueStatus['queue_sizes']);

        // Verify recent statistics structure
        $this->assertArrayHasKey('last_hour', $queueStatus['recent_statistics']);
        $this->assertArrayHasKey('last_24_hours', $queueStatus['recent_statistics']);
        $this->assertArrayHasKey('today', $queueStatus['recent_statistics']);
    }
}
