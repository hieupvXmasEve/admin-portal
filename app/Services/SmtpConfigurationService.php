<?php

namespace App\Services;

use App\Models\EmailConfiguration;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Exception;

class SmtpConfigurationService
{
    /**
     * Create a new SMTP configuration
     */
    public function create(array $data): EmailConfiguration
    {
        $this->validateConfiguration($data);

        $configuration = EmailConfiguration::create($data);

        // If this is the first configuration or marked as active, set it as active
        if ($data['is_active'] ?? false || EmailConfiguration::count() === 1) {
            $configuration->setAsActive();
        }

        return $configuration;
    }

    /**
     * Update an existing SMTP configuration
     */
    public function update(EmailConfiguration $configuration, array $data): EmailConfiguration
    {
        $this->validateConfiguration($data, $configuration->id);

        $configuration->update($data);

        // If marked as active, set it as the active configuration
        if ($data['is_active'] ?? false) {
            $configuration->setAsActive();
        }

        return $configuration->fresh();
    }

    /**
     * Delete an SMTP configuration
     */
    public function delete(EmailConfiguration $configuration): bool
    {
        // Prevent deletion of active configuration if it's the only one
        if ($configuration->is_active && EmailConfiguration::where('id', '!=', $configuration->id)->count() === 0) {
            throw new Exception('Cannot delete the only active email configuration.');
        }

        // If deleting active configuration, activate another one
        if ($configuration->is_active) {
            $nextConfig = EmailConfiguration::where('id', '!=', $configuration->id)->first();
            if ($nextConfig) {
                $nextConfig->setAsActive();
            }
        }

        return $configuration->delete();
    }

    /**
     * Get all SMTP configurations
     */
    public function getAll(): \Illuminate\Database\Eloquent\Collection
    {
        return EmailConfiguration::orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get active SMTP configuration
     */
    public function getActive(): ?EmailConfiguration
    {
        return EmailConfiguration::getActive();
    }

    /**
     * Test SMTP connection with given configuration
     */
    public function testConnection(EmailConfiguration $configuration): array
    {
        try {
            // Validate that configuration has required fields for testing
            if (!$configuration->isTestable()) {
                return [
                    'success' => false,
                    'message' => 'Configuration is missing required fields for testing (host, port, from_address).',
                    'error_code' => 'MISSING_REQUIRED_FIELDS'
                ];
            }

            // Create transport with configuration
            $transport = new EsmtpTransport(
                $configuration->host,
                $configuration->port,
                $configuration->encryption === 'ssl'
            );

            // Set encryption if specified
            if ($configuration->encryption === 'tls') {
                $transport->setEncryption('tls');
            }

            // Set authentication if provided
            if ($configuration->username && $configuration->password) {
                $transport->setUsername($configuration->username);
                $transport->setPassword($configuration->password);
            }

            // Test the connection by starting transport
            $transport->start();
            $transport->stop();

            // Update configuration with successful test result
            $configuration->update([
                'last_tested_at' => now(),
                'test_result' => 'success'
            ]);

            return [
                'success' => true,
                'message' => 'SMTP connection successful.',
                'tested_at' => $configuration->last_tested_at->toISOString()
            ];

        } catch (TransportException $e) {
            $errorMessage = $this->parseTransportError($e->getMessage());

            // Update configuration with failed test result
            $configuration->update([
                'last_tested_at' => now(),
                'test_result' => 'failed: ' . $errorMessage
            ]);

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => 'TRANSPORT_ERROR',
                'technical_details' => $e->getMessage()
            ];

        } catch (Exception $e) {
            // Update configuration with failed test result
            $configuration->update([
                'last_tested_at' => now(),
                'test_result' => 'failed: ' . $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'error_code' => 'GENERAL_ERROR',
                'technical_details' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection with array data (before saving)
     */
    public function testConnectionWithData(array $data): array
    {
        try {
            $this->validateConfiguration($data);

            // Create temporary configuration object for testing
            $tempConfig = new EmailConfiguration($data);

            return $this->testConnection($tempConfig);

        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => 'Configuration validation failed.',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors()
            ];
        }
    }

    /**
     * Apply configuration to Laravel Mail system
     */
    public function applyConfiguration(EmailConfiguration $configuration): void
    {
        $mailConfig = $configuration->toMailConfig();

        // Update runtime mail configuration
        Config::set('mail.mailers.smtp', $mailConfig);
        Config::set('mail.default', 'smtp');
        Config::set('mail.from', $mailConfig['from']);

        // Purge mail manager to force reload
        Mail::purge('smtp');
    }

    /**
     * Validate SMTP configuration data
     */
    public function validateConfiguration(array $data, ?int $excludeId = null): void
    {
        $rules = EmailConfiguration::validationRules();

        // Add unique rule for name if needed
        if (isset($data['name'])) {
            $nameRule = 'required|string|max:255|unique:email_configurations,name';
            if ($excludeId) {
                $nameRule .= ',' . $excludeId;
            }
            $rules['name'] = $nameRule;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional business logic validation
        $this->validateBusinessRules($data);
    }

    /**
     * Validate business rules for SMTP configuration
     */
    private function validateBusinessRules(array $data): void
    {
        // Validate port ranges for different encryption types
        if (isset($data['port']) && isset($data['encryption'])) {
            $port = (int) $data['port'];
            $encryption = $data['encryption'];

            $commonPorts = [
                'none' => [25, 587],
                'tls' => [587, 2587],
                'ssl' => [465, 993, 995]
            ];

            if (isset($commonPorts[$encryption]) && !in_array($port, $commonPorts[$encryption])) {
                // This is a warning, not a hard validation error
                // We'll allow it but could log a warning
            }
        }

        // Validate daily and rate limits
        if (isset($data['daily_limit']) && isset($data['rate_limit'])) {
            $dailyLimit = (int) $data['daily_limit'];
            $rateLimit = (int) $data['rate_limit'];

            if ($rateLimit > $dailyLimit) {
                throw ValidationException::withMessages([
                    'rate_limit' => ['Rate limit cannot exceed daily limit.']
                ]);
            }
        }

        // Validate from_address domain if host is provided
        if (isset($data['from_address']) && isset($data['host'])) {
            $fromDomain = substr(strrchr($data['from_address'], "@"), 1);
            $host = $data['host'];

            // Check if from_address domain matches or is subdomain of host
            if (!empty($fromDomain) && !str_contains($host, $fromDomain) && !str_contains($fromDomain, $host)) {
                // This is a warning, not a hard validation error
                // Some SMTP servers allow sending from different domains
            }
        }
    }

    /**
     * Parse transport error messages to user-friendly format
     */
    private function parseTransportError(string $error): string
    {
        $errorMappings = [
            'Connection refused' => 'Unable to connect to SMTP server. Please check host and port.',
            'Connection timed out' => 'Connection to SMTP server timed out. Please check host and port.',
            'Authentication failed' => 'SMTP authentication failed. Please check username and password.',
            'SSL/TLS handshake failed' => 'SSL/TLS connection failed. Please check encryption settings.',
            'Certificate verification failed' => 'SSL certificate verification failed. Please check server certificate.',
            'Username/Password not accepted' => 'SMTP credentials were rejected. Please check username and password.',
            'Relay access denied' => 'SMTP server denied relay access. Please check server configuration.',
        ];

        foreach ($errorMappings as $pattern => $message) {
            if (str_contains(strtolower($error), strtolower($pattern))) {
                return $message;
            }
        }

        // Return original error if no mapping found
        return 'SMTP connection failed: ' . $error;
    }

    /**
     * Get configuration statistics
     */
    public function getStatistics(): array
    {
        $total = EmailConfiguration::count();
        $active = EmailConfiguration::where('is_active', true)->count();
        $tested = EmailConfiguration::whereNotNull('last_tested_at')->count();
        $successful = EmailConfiguration::where('test_result', 'success')->count();

        return [
            'total_configurations' => $total,
            'active_configurations' => $active,
            'tested_configurations' => $tested,
            'successful_tests' => $successful,
            'test_success_rate' => $tested > 0 ? round(($successful / $tested) * 100, 2) : 0
        ];
    }

    /**
     * Rotate credentials for a configuration
     */
    public function rotateCredentials(EmailConfiguration $configuration, string $newPassword): EmailConfiguration
    {
        $configuration->update([
            'password' => $newPassword,
            'last_tested_at' => null,
            'test_result' => null
        ]);

        return $configuration->fresh();
    }

    /**
     * Rotate password encryption for enhanced security
     */
    public function rotatePasswordEncryption(EmailConfiguration $configuration): bool
    {
        return $configuration->rotatePasswordEncryption();
    }

    /**
     * Check configurations that need password rotation
     */
    public function getConfigurationsNeedingRotation(int $maxAgeInDays = 90): \Illuminate\Database\Eloquent\Collection
    {
        return EmailConfiguration::all()->filter(function ($config) use ($maxAgeInDays) {
            return $config->needsPasswordRotation($maxAgeInDays);
        });
    }

    /**
     * Bulk rotate password encryption for all configurations
     */
    public function bulkRotatePasswordEncryption(int $maxAgeInDays = 90): array
    {
        $configurations = $this->getConfigurationsNeedingRotation($maxAgeInDays);
        $results = [
            'total' => $configurations->count(),
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($configurations as $configuration) {
            try {
                if ($this->rotatePasswordEncryption($configuration)) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to rotate encryption for configuration: {$configuration->name}";
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error rotating encryption for {$configuration->name}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Validate password integrity for all configurations
     */
    public function validateAllPasswordIntegrity(): array
    {
        $configurations = EmailConfiguration::whereNotNull('password')->get();
        $results = [
            'total' => $configurations->count(),
            'valid' => 0,
            'invalid' => 0,
            'invalid_configurations' => []
        ];

        foreach ($configurations as $configuration) {
            if ($configuration->validatePasswordIntegrity()) {
                $results['valid']++;
            } else {
                $results['invalid']++;
                $results['invalid_configurations'][] = [
                    'id' => $configuration->id,
                    'name' => $configuration->name,
                    'encrypted_at' => $configuration->password_encrypted_at?->toISOString()
                ];
            }
        }

        return $results;
    }

    /**
     * Get security audit report for all configurations
     */
    public function getSecurityAuditReport(): array
    {
        $configurations = EmailConfiguration::all();
        $report = [
            'total_configurations' => $configurations->count(),
            'configurations_with_passwords' => 0,
            'configurations_needing_rotation' => 0,
            'configurations_with_backups' => 0,
            'configurations_with_integrity_issues' => 0,
            'encryption_metadata' => []
        ];

        foreach ($configurations as $configuration) {
            if (!empty($configuration->password)) {
                $report['configurations_with_passwords']++;

                if ($configuration->needsPasswordRotation()) {
                    $report['configurations_needing_rotation']++;
                }

                if (!empty($configuration->credential_backup)) {
                    $report['configurations_with_backups']++;
                }

                if (!$configuration->validatePasswordIntegrity()) {
                    $report['configurations_with_integrity_issues']++;
                }

                $report['encryption_metadata'][] = [
                    'id' => $configuration->id,
                    'name' => $configuration->name,
                    'metadata' => $configuration->getEncryptionMetadata()
                ];
            }
        }

        return $report;
    }
}
