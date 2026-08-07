<?php

namespace App\Console\Commands;

use App\Services\EmailService;
use App\Services\SmtpConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Exception;

class TestEmailConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-configuration
                            {--to= : Email address to send test email to}
                            {--smtp-only : Only test SMTP connection without sending email}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email system configuration and SMTP connectivity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Email System Configuration...');
        $this->newLine();

        $allPassed = true;

        // Test 1: Environment Configuration
        $allPassed &= $this->testEnvironmentConfiguration();

        // Test 2: SMTP Connection
        $allPassed &= $this->testSmtpConnection();

        // Test 3: Email Services
        $allPassed &= $this->testEmailServices();

        // Test 4: Queue Configuration
        $allPassed &= $this->testQueueConfiguration();

        // Test 5: Send Test Email (if requested)
        if (!$this->option('smtp-only') && $this->option('to')) {
            $allPassed &= $this->sendTestEmail($this->option('to'));
        }

        $this->newLine();

        if ($allPassed) {
            $this->info('✅ All email configuration tests passed!');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Some email configuration tests failed. Please check the output above.');
            return Command::FAILURE;
        }
    }

    /**
     * Test environment configuration
     */
    private function testEnvironmentConfiguration(): bool
    {
        $this->info('📋 Testing Environment Configuration...');

        $requiredVars = [
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_HOST' => config('mail.mailers.smtp.host'),
            'MAIL_PORT' => config('mail.mailers.smtp.port'),
            'MAIL_USERNAME' => config('mail.mailers.smtp.username'),
            'MAIL_PASSWORD' => config('mail.mailers.smtp.password'),
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
            'MAIL_FROM_NAME' => config('mail.from.name'),
        ];

        $allValid = true;

        foreach ($requiredVars as $var => $value) {
            if (empty($value) || $value === 'null') {
                $this->error("  ❌ {$var} is not set or empty");
                $allValid = false;
            } else {
                if ($this->option('verbose')) {
                    // Mask password for security
                    $displayValue = $var === 'MAIL_PASSWORD' ? str_repeat('*', strlen($value)) : $value;
                    $this->info("  ✅ {$var}: {$displayValue}");
                } else {
                    $this->info("  ✅ {$var} is configured");
                }
            }
        }

        // Check encryption setting
        $encryption = config('mail.mailers.smtp.encryption');
        if (in_array($encryption, ['tls', 'ssl'])) {
            $this->info("  ✅ MAIL_ENCRYPTION: {$encryption}");
        } else {
            $this->warn("  ⚠️  MAIL_ENCRYPTION is not set to TLS or SSL (current: {$encryption})");
        }

        return $allValid;
    }

    /**
     * Test SMTP connection
     */
    private function testSmtpConnection(): bool
    {
        $this->info('🔌 Testing SMTP Connection...');

        try {
            // Create a test transport
            $transport = Mail::getSwiftMailer()->getTransport();

            if (method_exists($transport, 'start')) {
                $transport->start();
                $this->info('  ✅ SMTP connection established successfully');

                if (method_exists($transport, 'stop')) {
                    $transport->stop();
                }

                return true;
            } else {
                // For newer versions of Laravel/Symfony Mailer
                $this->info('  ✅ SMTP configuration appears valid (connection test not available for this mailer)');
                return true;
            }
        } catch (Exception $e) {
            $this->error('  ❌ SMTP connection failed: ' . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error('  Full error: ' . $e->getTraceAsString());
            }

            return false;
        }
    }

    /**
     * Test email services
     */
    private function testEmailServices(): bool
    {
        $this->info('🔧 Testing Email Services...');

        $services = [
            'EmailService' => \App\Services\EmailService::class,
            'SmtpConfigurationService' => \App\Services\SmtpConfigurationService::class,
            'NotificationService' => \App\Services\NotificationService::class,
        ];

        $allValid = true;

        foreach ($services as $name => $class) {
            try {
                $service = app($class);
                $this->info("  ✅ {$name} is resolvable");

                if ($this->option('verbose')) {
                    $this->info("    Class: {$class}");
                }
            } catch (Exception $e) {
                $this->error("  ❌ {$name} failed to resolve: " . $e->getMessage());
                $allValid = false;
            }
        }

        return $allValid;
    }

    /**
     * Test queue configuration
     */
    private function testQueueConfiguration(): bool
    {
        $this->info('📬 Testing Queue Configuration...');

        $queueConnection = config('queue.default');
        $this->info("  ℹ️  Queue connection: {$queueConnection}");

        if ($queueConnection === 'sync') {
            $this->warn('  ⚠️  Queue is set to sync - emails will be sent synchronously');
            $this->warn('     Consider using redis or database for better performance');
        } else {
            $this->info('  ✅ Queue is configured for asynchronous processing');
        }

        // Test queue connection
        try {
            $queue = app('queue');
            $connection = $queue->connection();
            $this->info('  ✅ Queue connection is working');

            if ($this->option('verbose')) {
                $this->info('    Queue driver: ' . get_class($connection));
            }

            return true;
        } catch (Exception $e) {
            $this->error('  ❌ Queue connection failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send test email
     */
    private function sendTestEmail(string $to): bool
    {
        $this->info("📧 Sending Test Email to {$to}...");

        try {
            Mail::raw(
                "This is a test email from the Academic Management System.\n\n" .
                "If you received this email, your email configuration is working correctly!\n\n" .
                "Sent at: " . now()->format('Y-m-d H:i:s') . "\n" .
                "Environment: " . config('app.env'),
                function ($message) use ($to) {
                    $message->to($to)
                           ->subject('Email Configuration Test - ' . config('app.name'));
                }
            );

            $this->info('  ✅ Test email sent successfully');
            $this->info("  📬 Check the inbox for {$to}");

            return true;
        } catch (Exception $e) {
            $this->error('  ❌ Failed to send test email: ' . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error('  Full error: ' . $e->getTraceAsString());
            }

            return false;
        }
    }
}
