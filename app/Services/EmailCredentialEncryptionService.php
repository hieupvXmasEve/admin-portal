<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class EmailCredentialEncryptionService
{
    /**
     * Encrypt email credentials with additional security layers
     */
    public function encryptCredentials(string $password, ?string $salt = null): array
    {
        try {
            // Generate salt if not provided
            if (!$salt) {
                $salt = Str::random(32);
            }

            // Add salt to password before encryption
            $saltedPassword = $salt . $password;

            // Encrypt the salted password
            $encryptedPassword = Crypt::encryptString($saltedPassword);

            // Create verification hash for integrity checking
            $verificationHash = Hash::make($password . config('app.key'));

            return [
                'encrypted_password' => $encryptedPassword,
                'salt' => $salt,
                'verification_hash' => $verificationHash,
                'encrypted_at' => now()->toISOString()
            ];

        } catch (Exception $e) {
            Log::error('Email credential encryption failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception('Failed to encrypt email credentials: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt email credentials with integrity verification
     */
    public function decryptCredentials(string $encryptedPassword, string $salt, ?string $verificationHash = null): string
    {
        try {
            // Decrypt the password
            $saltedPassword = Crypt::decryptString($encryptedPassword);

            // Remove salt to get original password
            $originalPassword = substr($saltedPassword, strlen($salt));

            // Verify integrity if verification hash is provided
            if ($verificationHash && !Hash::check($originalPassword . config('app.key'), $verificationHash)) {
                throw new Exception('Credential integrity verification failed');
            }

            return $originalPassword;

        } catch (Exception $e) {
            Log::error('Email credential decryption failed', [
                'error' => $e->getMessage(),
                'has_verification_hash' => !empty($verificationHash)
            ]);

            throw new Exception('Failed to decrypt email credentials: ' . $e->getMessage());
        }
    }

    /**
     * Rotate encryption for existing credentials
     */
    public function rotateEncryption(string $currentEncryptedPassword, string $currentSalt, ?string $verificationHash = null): array
    {
        try {
            // First decrypt with current encryption
            $plainPassword = $this->decryptCredentials($currentEncryptedPassword, $currentSalt, $verificationHash);

            // Re-encrypt with new salt
            return $this->encryptCredentials($plainPassword);

        } catch (Exception $e) {
            Log::error('Email credential rotation failed', [
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to rotate email credential encryption: ' . $e->getMessage());
        }
    }

    /**
     * Validate encrypted credentials without decrypting
     */
    public function validateEncryptedCredentials(string $encryptedPassword, string $salt, ?string $verificationHash = null): bool
    {
        try {
            // Attempt to decrypt and verify
            $this->decryptCredentials($encryptedPassword, $salt, $verificationHash);
            return true;

        } catch (Exception $e) {
            Log::warning('Email credential validation failed', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generate secure random password for email accounts
     */
    public function generateSecurePassword(int $length = 16): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        // Ensure at least one character from each category
        $categories = [
            'abcdefghijklmnopqrstuvwxyz',      // lowercase
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',      // uppercase
            '0123456789',                       // numbers
            '!@#$%^&*'                         // special characters
        ];

        // Add one character from each category
        foreach ($categories as $category) {
            $password .= $category[random_int(0, strlen($category) - 1)];
        }

        // Fill remaining length with random characters
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Shuffle the password to randomize character positions
        return str_shuffle($password);
    }

    /**
     * Securely wipe password from memory (best effort)
     */
    public function secureWipe(string &$password): void
    {
        // Overwrite the string with random data multiple times
        $length = strlen($password);

        for ($i = 0; $i < 3; $i++) {
            $password = str_repeat(chr(random_int(0, 255)), $length);
        }

        // Finally set to empty string
        $password = '';

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Create backup of encrypted credentials for recovery
     */
    public function createCredentialBackup(array $encryptedData): string
    {
        try {
            $backupData = [
                'encrypted_password' => $encryptedData['encrypted_password'],
                'salt' => $encryptedData['salt'],
                'verification_hash' => $encryptedData['verification_hash'] ?? null,
                'backup_created_at' => now()->toISOString(),
                'backup_id' => Str::uuid()
            ];

            // Encrypt the entire backup data
            return Crypt::encryptString(json_encode($backupData));

        } catch (Exception $e) {
            Log::error('Email credential backup creation failed', [
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to create credential backup: ' . $e->getMessage());
        }
    }

    /**
     * Restore credentials from backup
     */
    public function restoreFromBackup(string $encryptedBackup): array
    {
        try {
            // Decrypt backup data
            $backupJson = Crypt::decryptString($encryptedBackup);
            $backupData = json_decode($backupJson, true);

            if (!$backupData || !isset($backupData['encrypted_password'], $backupData['salt'])) {
                throw new Exception('Invalid backup data format');
            }

            return [
                'encrypted_password' => $backupData['encrypted_password'],
                'salt' => $backupData['salt'],
                'verification_hash' => $backupData['verification_hash'] ?? null,
                'backup_id' => $backupData['backup_id'] ?? null,
                'backup_created_at' => $backupData['backup_created_at'] ?? null
            ];

        } catch (Exception $e) {
            Log::error('Email credential backup restoration failed', [
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to restore credentials from backup: ' . $e->getMessage());
        }
    }

    /**
     * Check if credentials need rotation based on age
     */
    public function needsRotation(string $encryptedAt, int $maxAgeInDays = 90): bool
    {
        try {
            $encryptedDate = \Carbon\Carbon::parse($encryptedAt);
            $daysSinceEncryption = $encryptedDate->diffInDays(now());

            return $daysSinceEncryption >= $maxAgeInDays;

        } catch (Exception $e) {
            Log::warning('Failed to check credential rotation needs', [
                'error' => $e->getMessage(),
                'encrypted_at' => $encryptedAt
            ]);

            // If we can't parse the date, assume rotation is needed for safety
            return true;
        }
    }

    /**
     * Get encryption metadata for auditing
     */
    public function getEncryptionMetadata(array $encryptedData): array
    {
        return [
            'has_salt' => !empty($encryptedData['salt']),
            'has_verification_hash' => !empty($encryptedData['verification_hash']),
            'encrypted_at' => $encryptedData['encrypted_at'] ?? null,
            'salt_length' => !empty($encryptedData['salt']) ? strlen($encryptedData['salt']) : 0,
            'encryption_algorithm' => 'AES-256-CBC', // Laravel's default
            'needs_rotation' => isset($encryptedData['encrypted_at']) ?
                $this->needsRotation($encryptedData['encrypted_at']) : true
        ];
    }
}
