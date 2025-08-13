<?php

namespace App\Services;

use App\Models\EmailConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Exception\TransportException;

class SmtpConfigurationService
{
    /**
     * Create a new SMTP configuration
     */
    public function createConfiguration(array $data): EmailConfiguration
    {
        // Validate the data
        $this->validateConfigurationData($data);

        // Create the configuration
        $config = EmailConfiguration::create($data);

        Log::info('SMTP configuration created', [
            'config_id' => $config->id,
            'name' => $config->name,
            'host' => $config->host,
        ]);

        return $config;
    }

    /**
     * Update an existing SMTP configuration
     */
    public function updateConfiguration(EmailConfiguration $config, array $data): EmailConfiguration
    {
        // Validate the data
        $this->validateConfigurationData($data, $config->id);

        // Update the configuration
        $config->update($data);

        Log::info('SMTP configuration updated', [
            'config_id' => $config->id,
            'name' => $config->name,
            'host' => $config->host,
        ]);

        return $config->fresh();
    }

    /**
     * Delete an SMTP configuration
     */
    public function deleteConfiguration(EmailConfiguration $config): bool
    {
        if ($config->is_active) {
            throw new \InvalidArgumentException('Cannot delete active SMTP configuration');
        }

        $configId = $config->id;
        $configName = $config->name;
        
        $deleted = $config->delete();

        if ($deleted) {
            Log::info('SMTP configuration deleted', [
                'config_id' => $configId,
                'name' => $configName,
            ]);
        }

        return $deleted;
    }

    /**
     * Set a configuration as active
     */
    public function setActiveConfiguration(EmailConfiguration $config): bool
    {
        $result = $config->setAsActive();

        if ($result) {
            Log::info('SMTP configuration set as active', [
                'config_id' => $config->id,
                'name' => $config->name,
                'host' => $config->host,
            ]);
        }

        return $result;
    }

    /**
     * Test SMTP connection
     */
    public function testConnection(EmailConfiguration $config): array
    {
        try {
            if (!$config->isTestable()) {
                return [
                    'success' => false,
                    'message' => 'Configuration is not complete for testing',
                    'errors' => ['Missing required fields for SMTP testing'],
                ];
            }

            // Create SMTP transport for testing
            $transport = $this->createSmtpTransport($config);

            // Test the connection with timeout
            $startTime = microtime(true);
            $this->testSmtpTransport($transport, $config);
            $connectionTime = round((microtime(true) - $startTime) * 1000, 2);

            $testResult = [
                'success' => true,
                'message' => 'SMTP connection successful',
                'host' => $config->host,
                'port' => $config->port,
                'encryption' => $config->encryption,
                'connection_time_ms' => $connectionTime,
                'tested_at' => now()->toISOString(),
            ];

            // Update test result in configuration
            $config->update([
                'last_tested_at' => now(),
                'test_result' => json_encode($testResult),
            ]);

            Log::info('SMTP connection test successful', [
                'config_id' => $config->id,
                'host' => $config->host,
                'port' => $config->port,
                'connection_time_ms' => $connectionTime,
            ]);

            return $testResult;
        } catch (TransportException $e) {
            return $this->handleConnectionError($config, $e, 'SMTP Transport Error');
        } catch (\Exception $e) {
            return $this->handleConnectionError($config, $e, 'Connection Error');
        }
    }

    /**
     * Create SMTP transport for testing
     */
    protected function createSmtpTransport(EmailConfiguration $config): EsmtpTransport
    {
        $dsn = $this->buildSmtpDsn($config);
        $transport = EsmtpTransport::fromDsn($dsn);

        // Set timeout for connection testing
        $transport->setTimeout(10); // 10 seconds timeout

        return $transport;
    }

    /**
     * Build SMTP DSN from configuration
     */
    protected function buildSmtpDsn(EmailConfiguration $config): string
    {
        $scheme = 'smtp';
        if ($config->encryption === 'ssl') {
            $scheme = 'smtps';
        }

        $dsn = "{$scheme}://";

        if ($config->username && $config->password) {
            $dsn .= urlencode($config->username) . ':' . urlencode($config->password) . '@';
        }

        $dsn .= $config->host . ':' . $config->port;

        if ($config->encryption === 'tls') {
            $dsn .= '?encryption=tls';
        }

        return $dsn;
    }

    /**
     * Test SMTP transport connection
     */
    protected function testSmtpTransport(EsmtpTransport $transport, EmailConfiguration $config): void
    {
        // Start the transport to test connection
        $transport->start();

        // If we get here, the connection was successful
        // We can optionally send a test EHLO command
        try {
            // The transport start() method already tests the connection
            // Additional validation could be added here if needed
        } finally {
            // Always stop the transport
            $transport->stop();
        }
    }

    /**
     * Handle connection errors
     */
    protected function handleConnectionError(EmailConfiguration $config, \Exception $e, string $errorType): array
    {
        $errorMessage = $this->parseErrorMessage($e->getMessage());

        $errorResult = [
            'success' => false,
            'message' => "{$errorType}: {$errorMessage}",
            'error' => $e->getMessage(),
            'error_type' => $errorType,
            'tested_at' => now()->toISOString(),
        ];

        // Update test result in configuration
        $config->update([
            'last_tested_at' => now(),
            'test_result' => json_encode($errorResult),
        ]);

        Log::error('SMTP connection test failed', [
            'config_id' => $config->id,
            'host' => $config->host,
            'port' => $config->port,
            'error_type' => $errorType,
            'error' => $e->getMessage(),
        ]);

        return $errorResult;
    }

    /**
     * Parse error message to provide user-friendly feedback
     */
    protected function parseErrorMessage(string $error): string
    {
        // Common SMTP error patterns and user-friendly messages
        $patterns = [
            '/Connection refused/' => 'Connection refused - check host and port',
            '/Connection timed out/' => 'Connection timed out - check host and firewall',
            '/Authentication failed/' => 'Authentication failed - check username and password',
            '/SSL.*error/' => 'SSL/TLS error - check encryption settings',
            '/Name or service not known/' => 'Host not found - check hostname',
            '/Network is unreachable/' => 'Network unreachable - check network connectivity',
        ];

        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $error)) {
                return $message;
            }
        }

        return 'Connection failed - check configuration';
    }

    /**
     * Get all SMTP configurations
     */
    public function getAllConfigurations(): \Illuminate\Database\Eloquent\Collection
    {
        return EmailConfiguration::orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get active SMTP configuration
     */
    public function getActiveConfiguration(): ?EmailConfiguration
    {
        return EmailConfiguration::getActive();
    }

    /**
     * Validate configuration data
     */
    protected function validateConfigurationData(array $data, ?int $excludeId = null): void
    {
        $rules = EmailConfiguration::validationRules();
        
        // Add unique name validation
        $nameRule = 'required|string|max:255|unique:email_configurations,name';
        if ($excludeId) {
            $nameRule .= ',' . $excludeId;
        }
        $rules['name'] = $nameRule;

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Get configuration statistics
     */
    public function getConfigurationStatistics(): array
    {
        $total = EmailConfiguration::count();
        $active = EmailConfiguration::where('is_active', true)->count();
        $tested = EmailConfiguration::whereNotNull('last_tested_at')->count();
        $working = EmailConfiguration::whereNotNull('last_tested_at')
            ->where('test_result', 'like', '%success%')
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'tested' => $tested,
            'working' => $working,
            'untested' => $total - $tested,
        ];
    }

    /**
     * Export configuration for backup (without sensitive data)
     */
    public function exportConfiguration(EmailConfiguration $config): array
    {
        return [
            'name' => $config->name,
            'host' => $config->host,
            'port' => $config->port,
            'username' => $config->username,
            'encryption' => $config->encryption,
            'from_address' => $config->from_address,
            'from_name' => $config->from_name,
            'daily_limit' => $config->daily_limit,
            'rate_limit' => $config->rate_limit,
            'exported_at' => now()->toISOString(),
        ];
    }

    /**
     * Import configuration from backup
     */
    public function importConfiguration(array $data): EmailConfiguration
    {
        // Remove export metadata
        unset($data['exported_at']);
        
        // Set as inactive by default
        $data['is_active'] = false;
        
        // Ensure unique name
        $originalName = $data['name'];
        $counter = 1;
        while (EmailConfiguration::where('name', $data['name'])->exists()) {
            $data['name'] = $originalName . ' (Imported ' . $counter . ')';
            $counter++;
        }

        return $this->createConfiguration($data);
    }
}
