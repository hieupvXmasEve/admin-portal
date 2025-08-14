<?php

namespace Tests\Feature;

use App\Models\EmailConfiguration;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EmailRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected EmailService $emailService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emailService = app(EmailService::class);
    }

    /** @test */
    public function it_implements_per_user_rate_limiting()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 100, // High global limit
            'daily_limit' => 1000
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Set per-user rate limit (e.g., 3 emails per minute)
        $perUserLimit = 3;
        $timeWindow = 60; // seconds

        // User 1 sends emails up to their limit
        for ($i = 0; $i < $perUserLimit; $i++) {
            $emailLog = $this->emailService->sendSingleEmail(
                'recipient@example.com',
                "Test Email {$i}",
                'Test content',
                null,
                [],
                $user1
            );
            $this->assertInstanceOf(EmailLog::class, $emailLog);
        }

        // User 1's next email should be rate limited
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User rate limit exceeded');

        $this->emailService->sendSingleEmail(
            'recipient@example.com',
            'Rate Limited Email',
            'Test content',
            null,
            [],
            $user1
        );
    }

    /** @test */
    public function it_implements_per_recipient_rate_limiting()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 100,
            'daily_limit' => 1000
        ]);

        $recipient = 'limited@example.com';
        $perRecipientLimit = 2; // Max 2 emails per recipient per hour

        // Send emails up to recipient limit
        for ($i = 0; $i < $perRecipientLimit; $i++) {
            $emailLog = $this->emailService->sendSingleEmail(
                $recipient,
                "Test Email {$i}",
                'Test content'
            );
            $this->assertInstanceOf(EmailLog::class, $emailLog);
        }

        // Next email to same recipient should be rate limited
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient rate limit exceeded');

        $this->emailService->sendSingleEmail(
            $recipient,
            'Rate Limited Email',
            'Test content'
        );
    }

    /** @test */
    public function it_implements_adaptive_rate_limiting_based_on_bounce_rate()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 50,
            'daily_limit' => 1000
        ]);

        // Create email logs with high bounce rate
        $totalEmails = 20;
        $bouncedEmails = 15; // 75% bounce rate

        // Create successful emails
        EmailLog::factory()->count($totalEmails - $bouncedEmails)->create([
            'status' => EmailLog::STATUS_DELIVERED,
            'created_at' => now()->subHour()
        ]);

        // Create bounced emails
        EmailLog::factory()->count($bouncedEmails)->create([
            'status' => EmailLog::STATUS_BOUNCED,
            'created_at' => now()->subHour()
        ]);

        // High bounce rate should trigger adaptive rate limiting
        $currentBounceRate = $this->emailService->calculateBounceRate(now()->subDay(), now());
        $this->assertGreaterThan(0.5, $currentBounceRate); // Over 50% bounce rate

        // Rate limit should be automatically reduced
        $adaptiveLimit = $this->emailService->getAdaptiveRateLimit();
        $this->assertLessThan($config->rate_limit, $adaptiveLimit);

        // Verify reduced rate limit is enforced
        for ($i = 0; $i < $adaptiveLimit; $i++) {
            $emailLog = $this->emailService->sendSingleEmail(
                "test{$i}@example.com",
                'Test Email',
                'Test content'
            );
            $this->assertInstanceOf(EmailLog::class, $emailLog);
        }

        // Next email should be rate limited due to adaptive limiting
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Adaptive rate limit exceeded');

        $this->emailService->sendSingleEmail(
            'final@example.com',
            'Rate Limited Email',
            'Test content'
        );
    }

    /** @test */
    public function it_implements_burst_protection_with_token_bucket()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 10, // 10 emails per minute
            'daily_limit' => 1000
        ]);

        // Configure token bucket: 5 tokens, refill 1 token every 6 seconds
        $bucketCapacity = 5;
        $refillRate = 1; // tokens per 6 seconds

        // Should be able to send burst of emails up to bucket capacity
        for ($i = 0; $i < $bucketCapacity; $i++) {
            $emailLog = $this->emailService->sendSingleEmail(
                "burst{$i}@example.com",
                'Burst Email',
                'Test content'
            );
            $this->assertInstanceOf(EmailLog::class, $emailLog);
        }

        // Next email should be rate limited (bucket empty)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit exceeded - burst protection');

        $this->emailService->sendSingleEmail(
            'overflow@example.com',
            'Overflow Email',
            'Test content'
        );
    }

    /** @test */
    public function it_handles_rate_limit_reset_correctly()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 2, // Very low for testing
            'daily_limit' => 100
        ]);

        // Send emails up to rate limit
        for ($i = 0; $i < 2; $i++) {
            $emailLog = $this->emailService->sendSingleEmail(
                "test{$i}@example.com",
                'Test Email',
                'Test content'
            );
            $this->assertInstanceOf(EmailLog::class, $emailLog);
        }

        // Verify rate limit is hit
        try {
            $this->emailService->sendSingleEmail(
                'limited@example.com',
                'Rate Limited Email',
                'Test content'
            );
            $this->fail('Expected rate limit exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Rate limit exceeded', $e->getMessage());
        }

        // Simulate time passing (rate limit window reset)
        $this->travel(61)->seconds(); // Move forward 61 seconds

        // Should be able to send emails again after reset
        $emailLog = $this->emailService->sendSingleEmail(
            'reset@example.com',
            'Post-Reset Email',
            'Test content'
        );

        $this->assertInstanceOf(EmailLog::class, $emailLog);
    }

    /** @test */
    public function it_implements_priority_based_rate_limiting()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 3, // Very low for testing
            'daily_limit' => 100
        ]);

        // Send high priority emails (should bypass normal rate limiting)
        for ($i = 0; $i < 5; $i++) {
            $emailLog = $this->emailService->sendSingleEmail(
                "priority{$i}@example.com",
                'High Priority Email',
                'Urgent content',
                null,
                [],
                null,
                [],
                'high' // Priority parameter
            );
            $this->assertInstanceOf(EmailLog::class, $emailLog);
        }

        // Normal priority email should still be rate limited
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->emailService->sendSingleEmail(
            'normal@example.com',
            'Normal Priority Email',
            'Regular content',
            null,
            [],
            null,
            [],
            'normal'
        );
    }

    /** @test */
    public function it_handles_bounce_based_rate_limiting()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 50,
            'daily_limit' => 1000
        ]);

        $recipient = 'bouncy@example.com';

        // Create history of bounces for this recipient
        EmailLog::factory()->count(3)->create([
            'recipient' => $recipient,
            'status' => EmailLog::STATUS_BOUNCED,
            'bounce_type' => 'hard',
            'created_at' => now()->subHours(2)
        ]);

        // Recipient with high bounce rate should be rate limited more aggressively
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Recipient has high bounce rate');

        $this->emailService->sendSingleEmail(
            $recipient,
            'Test Email',
            'Test content'
        );
    }

    /** @test */
    public function it_implements_domain_based_rate_limiting()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 100,
            'daily_limit' => 1000
        ]);

        $domain = 'ratelimited.com';
        $domainLimit = 2; // Max 2 emails per domain per minute

        // Send emails up to domain limit
        for ($i = 0; $i < $domainLimit; $i++) {
            $emailLog = $this->emailService->sendSingleEmail(
                "user{$i}@{$domain}",
                'Domain Test Email',
                'Test content'
            );
            $this->assertInstanceOf(EmailLog::class, $emailLog);
        }

        // Next email to same domain should be rate limited
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain rate limit exceeded');

        $this->emailService->sendSingleEmail(
            "user3@{$domain}",
            'Rate Limited Email',
            'Test content'
        );
    }

    /** @test */
    public function it_provides_rate_limit_status_information()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 10,
            'daily_limit' => 100
        ]);

        // Send some emails
        for ($i = 0; $i < 3; $i++) {
            $this->emailService->sendSingleEmail(
                "test{$i}@example.com",
                'Test Email',
                'Test content'
            );
        }

        // Get rate limit status
        $status = $this->emailService->getRateLimitStatus();

        $this->assertArrayHasKey('current_usage', $status);
        $this->assertArrayHasKey('limit', $status);
        $this->assertArrayHasKey('remaining', $status);
        $this->assertArrayHasKey('reset_time', $status);
        $this->assertArrayHasKey('daily_usage', $status);
        $this->assertArrayHasKey('daily_limit', $status);

        $this->assertEquals(3, $status['current_usage']);
        $this->assertEquals(10, $status['limit']);
        $this->assertEquals(7, $status['remaining']);
        $this->assertEquals(3, $status['daily_usage']);
        $this->assertEquals(100, $status['daily_limit']);
    }

    /** @test */
    public function it_handles_rate_limit_exceptions_gracefully()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 1,
            'daily_limit' => 100
        ]);

        // Send one email (should succeed)
        $emailLog = $this->emailService->sendSingleEmail(
            'test@example.com',
            'Test Email',
            'Test content'
        );
        $this->assertInstanceOf(EmailLog::class, $emailLog);

        // Try to send another (should be rate limited)
        try {
            $this->emailService->sendSingleEmail(
                'test2@example.com',
                'Rate Limited Email',
                'Test content'
            );
            $this->fail('Expected rate limit exception');
        } catch (\InvalidArgumentException $e) {
            // Verify exception contains helpful information
            $this->assertStringContainsString('Rate limit exceeded', $e->getMessage());

            // Verify rate limit status is available in exception data
            $this->assertArrayHasKey('rate_limit_status', $e->getTrace()[0]['args'] ?? []);
        }

        // Verify failed attempt is logged
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'email_security',
            'description' => 'Rate limit exceeded'
        ]);
    }

    /** @test */
    public function it_implements_sliding_window_rate_limiting()
    {
        Queue::fake();

        $config = EmailConfiguration::factory()->create([
            'is_active' => true,
            'rate_limit' => 5, // 5 emails per minute
            'daily_limit' => 1000
        ]);

        // Send emails at different times within the window
        $this->emailService->sendSingleEmail('test1@example.com', 'Test 1', 'Content');

        $this->travel(10)->seconds();
        $this->emailService->sendSingleEmail('test2@example.com', 'Test 2', 'Content');

        $this->travel(10)->seconds();
        $this->emailService->sendSingleEmail('test3@example.com', 'Test 3', 'Content');

        $this->travel(10)->seconds();
        $this->emailService->sendSingleEmail('test4@example.com', 'Test 4', 'Content');

        $this->travel(10)->seconds();
        $this->emailService->sendSingleEmail('test5@example.com', 'Test 5', 'Content');

        // At this point, we've sent 5 emails in 40 seconds
        // Next email should be rate limited
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->emailService->sendSingleEmail('test6@example.com', 'Test 6', 'Content');
    }
}
