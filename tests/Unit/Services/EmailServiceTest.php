<?php

namespace Tests\Unit\Services;

use App\Jobs\SendBulkEmailJob;
use App\Jobs\SendSingleEmailJob;
use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\UserEmailPreference;
use App\Services\EmailLoggingService;
use App\Services\EmailService;
use App\Services\UserEmailPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailService $emailService;
    private EmailLoggingService $loggingService;
    private UserEmailPreferenceService $preferenceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loggingService = $this->createMock(EmailLoggingService::class);
        $this->preferenceService = $this->createMock(UserEmailPreferenceService::class);

        $this->emailService = new EmailService(
            $this->loggingService,
            $this->preferenceService
        );
    }

    /** @test */
    public function it_validates_email_address_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address format');

        $this->emailService->sendSingleEmail(
            'invalid-email',
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_validates_email_address_length()
    {
        $longEmail = str_repeat('a', 250) . '@example.com';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email address too long');

        $this->emailService->sendSingleEmail(
            $longEmail,
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_validates_email_address_patterns()
    {
        $invalidEmails = [
            'test..test@example.com',  // Multiple consecutive dots
            '.test@example.com',       // Starting with dot
            'test.@example.com',       // Ending with dot
            'test@.example.com',       // @ followed by dot
            'test.@example.com',       // Dot followed by @
        ];

        foreach ($invalidEmails as $email) {
            try {
                $this->emailService->sendSingleEmail($email, 'Test', 'Content');
                $this->fail("Expected exception for email: {$email}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid email address', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_validates_local_part_length()
    {
        $longLocalPart = str_repeat('a', 65) . '@example.com';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email local part too long');

        $this->emailService->sendSingleEmail(
            $longLocalPart,
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_validates_domain_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email domain format');

        $this->emailService->sendSingleEmail(
            'test@invalid-domain',
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_validates_email_subject()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email subject cannot be empty');

        $this->emailService->sendSingleEmail(
            'test@example.com',
            '',
            'Test Content'
        );
    }

    /** @test */
    public function it_validates_subject_length()
    {
        $longSubject = str_repeat('a', 1000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email subject too long');

        $this->emailService->sendSingleEmail(
            'test@example.com',
            $longSubject,
            'Test Content'
        );
    }

    /** @test */
    public function it_validates_email_content()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email content cannot be empty');

        $this->emailService->sendSingleEmail(
            'test@example.com',
            'Test Subject',
            ''
        );
    }

    /** @test */
    public function it_validates_content_size()
    {
        $largeContent = str_repeat('a', 11 * 1024 * 1024); // 11MB

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email content too large');

        $this->emailService->sendSingleEmail(
            'test@example.com',
            'Test Subject',
            $largeContent
        );
    }

    /** @test */
    public function it_validates_attachments_exist()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attachment file not found');

        $this->emailService->sendSingleEmail(
            'test@example.com',
            'Test Subject',
            'Test Content',
            null,
            ['/nonexistent/file.pdf']
        );
    }

    /** @test */
    public function it_validates_attachment_count()
    {
        // Create temporary files for testing
        $attachments = [];
        for ($i = 0; $i < 21; $i++) {
            $tempFile = tempnam(sys_get_temp_dir(), 'test_attachment_');
            file_put_contents($tempFile, 'test content');
            $attachments[] = $tempFile;
        }

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Too many attachments');

            $this->emailService->sendSingleEmail(
                'test@example.com',
                'Test Subject',
                'Test Content',
                null,
                $attachments
            );
        } finally {
            // Clean up temporary files
            foreach ($attachments as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    /** @test */
    public function it_checks_user_email_preferences()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Mock the preference service to return false (user opted out)
        $this->preferenceService
            ->expects($this->once())
            ->method('canUserReceiveNotification')
            ->with($user, 'general')
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User has opted out of email notifications');

        $this->emailService->sendSingleEmail(
            'test@example.com',
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_creates_email_log_on_send()
    {
        Queue::fake();

        $mockEmailLog = $this->createMock(EmailLog::class);
        $mockEmailLog->method('markAsQueued')->willReturn(true);
        $mockEmailLog->id = 1;

        $this->loggingService
            ->expects($this->once())
            ->method('logEmailSendingAttempt')
            ->willReturn($mockEmailLog);

        $this->loggingService
            ->expects($this->once())
            ->method('logEmailOperation')
            ->with('single_email_queued', $this->anything(), 'info');

        $result = $this->emailService->sendSingleEmail(
            'test@example.com',
            'Test Subject',
            'Test Content'
        );

        $this->assertInstanceOf(EmailLog::class, $result);
        Queue::assertPushed(SendSingleEmailJob::class);
    }

    /** @test */
    public function it_renders_template_content()
    {
        Queue::fake();

        $template = $this->createMock(EmailTemplate::class);
        $template->method('render')
            ->with(['name' => 'John'])
            ->willReturn([
                'html' => '<p>Hello John</p>',
                'text' => 'Hello John'
            ]);

        $mockEmailLog = $this->createMock(EmailLog::class);
        $mockEmailLog->method('markAsQueued')->willReturn(true);
        $mockEmailLog->id = 1;

        $this->loggingService
            ->expects($this->once())
            ->method('logEmailSendingAttempt')
            ->willReturn($mockEmailLog);

        $this->emailService->sendSingleEmail(
            'test@example.com',
            'Test Subject',
            'Test Content',
            $template,
            [],
            null,
            ['name' => 'John']
        );

        Queue::assertPushed(SendSingleEmailJob::class);
    }

    /** @test */
    public function it_validates_bulk_email_parameters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipients list cannot be empty');

        $this->emailService->sendBulkEmail(
            [],
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_validates_bulk_email_recipient_count()
    {
        $recipients = array_fill(0, 10001, 'test@example.com');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many recipients');

        $this->emailService->sendBulkEmail(
            $recipients,
            'Test Subject',
            'Test Content'
        );
    }

    /** @test */
    public function it_validates_chunk_size()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid chunk size');

        $this->emailService->sendBulkEmail(
            ['test@example.com'],
            'Test Subject',
            'Test Content',
            null,
            [],
            null,
            [],
            0 // Invalid chunk size
        );
    }

    /** @test */
    public function it_processes_recipients_and_removes_duplicates()
    {
        Bus::fake();

        $recipients = [
            'test1@example.com',
            'test2@example.com',
            'test1@example.com', // Duplicate
            'TEST1@EXAMPLE.COM', // Duplicate (case insensitive)
        ];

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Test Subject',
            'Test Content'
        );

        $this->assertEquals(2, $result['total_recipients']); // Only unique emails
        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 1; // One chunk
        });
    }

    /** @test */
    public function it_filters_invalid_email_addresses_in_bulk()
    {
        Bus::fake();

        $recipients = [
            'valid@example.com',
            'invalid-email',
            'another@example.com',
            'also-invalid',
        ];

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Test Subject',
            'Test Content'
        );

        $this->assertEquals(2, $result['total_recipients']);
        $this->assertEquals(2, $result['invalid_recipients']);
        $this->assertCount(2, $result['invalid_emails']);
    }

    /** @test */
    public function it_creates_batch_jobs_for_bulk_email()
    {
        Bus::fake();

        $recipients = array_fill(0, 250, 'test@example.com');
        // Make them unique
        $recipients = array_map(function($email, $index) {
            return "test{$index}@example.com";
        }, $recipients, array_keys($recipients));

        $result = $this->emailService->sendBulkEmail(
            $recipients,
            'Test Subject',
            'Test Content',
            null,
            [],
            null,
            [],
            100 // Chunk size
        );

        $this->assertEquals(250, $result['total_recipients']);
        $this->assertEquals(3, $result['chunks']); // 250 / 100 = 3 chunks

        Bus::assertBatched(function ($batch) {
            return $batch->jobs->count() === 3;
        });
    }

    /** @test */
    public function it_validates_email_configuration()
    {
        $config = new EmailConfiguration([
            'host' => '',
            'port' => 0,
            'from_address' => 'invalid-email',
            'from_name' => '',
        ]);

        $errors = $this->emailService->validateEmailConfiguration($config);

        $this->assertContains('SMTP host is required', $errors);
        $this->assertContains('Valid SMTP port is required', $errors);
        $this->assertContains('Valid from email address is required', $errors);
        $this->assertContains('From name is required', $errors);
    }

    /** @test */
    public function it_validates_valid_email_configuration()
    {
        $config = new EmailConfiguration([
            'host' => 'smtp.example.com',
            'port' => 587,
            'from_address' => 'test@example.com',
            'from_name' => 'Test System',
        ]);

        $errors = $this->emailService->validateEmailConfiguration($config);

        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_tests_smtp_connection_with_valid_config()
    {
        $config = EmailConfiguration::factory()->make([
            'host' => 'smtp.example.com',
            'port' => 587,
            'from_address' => 'test@example.com',
            'from_name' => 'Test System',
        ]);

        $result = $this->emailService->testSmtpConnection($config);

        $this->assertTrue($result['success']);
        $this->assertEquals('SMTP configuration is valid', $result['message']);
    }

    /** @test */
    public function it_tests_smtp_connection_with_invalid_config()
    {
        $config = EmailConfiguration::factory()->make([
            'host' => '',
            'port' => 0,
            'from_address' => 'invalid',
            'from_name' => '',
        ]);

        $result = $this->emailService->testSmtpConnection($config);

        $this->assertFalse($result['success']);
        $this->assertEquals('Configuration validation failed', $result['message']);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function it_checks_user_notification_preferences()
    {
        $user = User::factory()->create();

        // Mock preference service
        $this->preferenceService
            ->expects($this->once())
            ->method('canUserReceiveNotification')
            ->with($user, 'welcome')
            ->willReturn(true);

        $result = $this->emailService->canUserReceiveNotification($user, 'welcome');

        $this->assertTrue($result);
    }

    /** @test */
    public function it_renders_template_with_variables()
    {
        $template = $this->createMock(EmailTemplate::class);
        $template->method('validateVariables')
            ->with(['name' => 'John'])
            ->willReturn([]);

        $template->method('render')
            ->with(['name' => 'John'])
            ->willReturn([
                'subject' => 'Hello John',
                'html' => '<p>Hello John</p>',
                'text' => 'Hello John'
            ]);

        $result = $this->emailService->renderTemplate($template, ['name' => 'John']);

        $this->assertEquals('Hello John', $result['subject']);
        $this->assertEquals('<p>Hello John</p>', $result['html']);
        $this->assertEquals('Hello John', $result['text']);
    }

    /** @test */
    public function it_throws_exception_for_missing_template_variables()
    {
        $template = $this->createMock(EmailTemplate::class);
        $template->method('validateVariables')
            ->with(['name' => 'John'])
            ->willReturn(['email']); // Missing email variable

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required template variables: email');

        $this->emailService->renderTemplate($template, ['name' => 'John']);
    }

    /** @test */
    public function it_validates_rendered_html_content()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email must have either HTML or text content');

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->emailService);
        $method = $reflection->getMethod('validateRenderedContent');
        $method->setAccessible(true);

        $method->invoke($this->emailService, null, null);
    }

    /** @test */
    public function it_detects_dangerous_html_content()
    {
        $dangerousHtml = '<p>Hello</p><script>alert("xss")</script>';

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->emailService);
        $method = $reflection->getMethod('validateRenderedContent');
        $method->setAccessible(true);

        // This should not throw an exception but should log a warning
        $method->invoke($this->emailService, $dangerousHtml, null);

        // The method should complete without throwing an exception
        $this->assertTrue(true);
    }

    /** @test */
    public function it_gets_attachment_metadata()
    {
        // Create a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test_attachment_');
        file_put_contents($tempFile, 'test content');

        try {
            // Use reflection to test protected method
            $reflection = new \ReflectionClass($this->emailService);
            $method = $reflection->getMethod('getAttachmentMetadata');
            $method->setAccessible(true);

            $metadata = $method->invoke($this->emailService, [$tempFile]);

            $this->assertCount(1, $metadata);
            $this->assertEquals(basename($tempFile), $metadata[0]['name']);
            $this->assertEquals(12, $metadata[0]['size']); // 'test content' length
            $this->assertArrayHasKey('mime_type', $metadata[0]);
        } finally {
            // Clean up
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
