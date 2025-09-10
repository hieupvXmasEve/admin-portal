<?php

namespace App\Models;

use App\Services\EmailCredentialEncryptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class EmailConfiguration extends AuditableModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'is_active',
        'daily_limit',
        'rate_limit',
        'last_tested_at',
        'test_result',
        'password_salt',
        'password_verification_hash',
        'password_encrypted_at',
        'credential_backup',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'port' => 'integer',
        'daily_limit' => 'integer',
        'rate_limit' => 'integer',
        'last_tested_at' => 'datetime',
        'password_encrypted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'password_salt',
        'password_verification_hash',
        'credential_backup',
    ];

    /**
     * Encrypt the password when setting it using enhanced encryption service
     */
    public function setPasswordAttribute(?string $value): void
    {
        if ($value !== null) {
            $encryptionService = app(EmailCredentialEncryptionService::class);

            // Create backup of old credentials if they exist
            if (!empty($this->attributes['password']) && !empty($this->attributes['password_salt'])) {
                $this->createCredentialBackup();
            }

            $encryptedData = $encryptionService->encryptCredentials($value);

            $this->attributes['password'] = $encryptedData['encrypted_password'];
            $this->attributes['password_salt'] = $encryptedData['salt'];
            $this->attributes['password_verification_hash'] = $encryptedData['verification_hash'];
            $this->attributes['password_encrypted_at'] = now()->format('Y-m-d H:i:s');
        } else {
            $this->attributes['password'] = null;
            $this->attributes['password_salt'] = null;
            $this->attributes['password_verification_hash'] = null;
            $this->attributes['password_encrypted_at'] = null;
        }
    }

    /**
     * Decrypt the password when getting it using enhanced encryption service
     */
    public function getPasswordAttribute(?string $value): ?string
    {
        if ($value !== null && !empty($this->attributes['password_salt'])) {
            try {
                $encryptionService = app(EmailCredentialEncryptionService::class);
                return $encryptionService->decryptCredentials(
                    $value,
                    $this->attributes['password_salt'],
                    $this->attributes['password_verification_hash'] ?? null
                );
            } catch (\Exception $e) {
                \Log::error('Failed to decrypt email password', [
                    'configuration_id' => $this->id,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }
        return null;
    }

    /**
     * Get the active email configuration
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Set this configuration as active and deactivate others
     */
    public function setAsActive(): bool
    {
        // Deactivate all other configurations
        static::where('id', '!=', $this->id)->update(['is_active' => false]);

        // Activate this configuration
        return $this->update(['is_active' => true]);
    }

    /**
     * Check if this configuration is testable
     */
    public function isTestable(): bool
    {
        return !empty($this->host) && !empty($this->port) && !empty($this->from_address);
    }

    /**
     * Get configuration as array for Laravel Mail
     */
    public function toMailConfig(): array
    {
        return [
            'transport' => 'smtp',
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'encryption' => $this->encryption === 'none' ? null : $this->encryption,
            'from' => [
                'address' => $this->from_address,
                'name' => $this->from_name,
            ],
        ];
    }

    /**
     * Validation rules for email configuration
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'encryption' => 'required|in:tls,ssl,none',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'daily_limit' => 'required|integer|min:1|max:10000',
            'rate_limit' => 'required|integer|min:1|max:1000',
            'is_active' => 'required|boolean',
        ];
    }

    /**
     * Rotate password encryption with new salt and encryption
     */
    public function rotatePasswordEncryption(): bool
    {
        if (empty($this->attributes['password']) || empty($this->attributes['password_salt'])) {
            return false;
        }

        try {
            $encryptionService = app(EmailCredentialEncryptionService::class);

            // Create backup before rotation
            $this->createCredentialBackup();

            // Rotate encryption
            $rotatedData = $encryptionService->rotateEncryption(
                $this->attributes['password'],
                $this->attributes['password_salt'],
                $this->attributes['password_verification_hash'] ?? null
            );

            // Update with new encrypted data
            $this->update([
                'password' => $rotatedData['encrypted_password'],
                'password_salt' => $rotatedData['salt'],
                'password_verification_hash' => $rotatedData['verification_hash'],
                'password_encrypted_at' => $rotatedData['encrypted_at'],
                'last_tested_at' => null, // Force retest after rotation
                'test_result' => null
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to rotate password encryption', [
                'configuration_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create backup of current credentials
     */
    public function createCredentialBackup(): bool
    {
        if (empty($this->attributes['password']) || empty($this->attributes['password_salt'])) {
            return false;
        }

        try {
            $encryptionService = app(EmailCredentialEncryptionService::class);

            $credentialData = [
                'encrypted_password' => $this->attributes['password'],
                'salt' => $this->attributes['password_salt'],
                'verification_hash' => $this->attributes['password_verification_hash'] ?? null,
                'encrypted_at' => $this->attributes['password_encrypted_at'] ?? now()->toISOString()
            ];

            $backup = $encryptionService->createCredentialBackup($credentialData);

            $this->update(['credential_backup' => $backup]);

            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to create credential backup', [
                'configuration_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore credentials from backup
     */
    public function restoreFromBackup(): bool
    {
        if (empty($this->credential_backup)) {
            return false;
        }

        try {
            $encryptionService = app(EmailCredentialEncryptionService::class);

            $restoredData = $encryptionService->restoreFromBackup($this->credential_backup);

            $this->update([
                'password' => $restoredData['encrypted_password'],
                'password_salt' => $restoredData['salt'],
                'password_verification_hash' => $restoredData['verification_hash'],
                'password_encrypted_at' => $restoredData['backup_created_at'] ?? now(),
                'last_tested_at' => null, // Force retest after restoration
                'test_result' => null
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to restore credentials from backup', [
                'configuration_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if password encryption needs rotation
     */
    public function needsPasswordRotation(int $maxAgeInDays = 90): bool
    {
        if (empty($this->password_encrypted_at)) {
            return true; // No encryption date means old format, needs rotation
        }

        $encryptionService = app(EmailCredentialEncryptionService::class);
        return $encryptionService->needsRotation($this->password_encrypted_at->toISOString(), $maxAgeInDays);
    }

    /**
     * Get encryption metadata for security auditing
     */
    public function getEncryptionMetadata(): array
    {
        $encryptionService = app(EmailCredentialEncryptionService::class);

        $data = [
            'encrypted_at' => $this->password_encrypted_at?->toISOString(),
            'salt' => $this->password_salt,
            'verification_hash' => $this->password_verification_hash
        ];

        return $encryptionService->getEncryptionMetadata($data);
    }

    /**
     * Validate password integrity without decrypting
     */
    public function validatePasswordIntegrity(): bool
    {
        if (empty($this->attributes['password']) || empty($this->attributes['password_salt'])) {
            return false;
        }

        $encryptionService = app(EmailCredentialEncryptionService::class);

        return $encryptionService->validateEncryptedCredentials(
            $this->attributes['password'],
            $this->attributes['password_salt'],
            $this->attributes['password_verification_hash'] ?? null
        );
    }

    /**
     * Custom activity descriptions for email configuration events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => "Created email configuration: {$this->name}",
            'updated' => "Updated email configuration: {$this->name}",
            'deleted' => "Deleted email configuration: {$this->name}",
            'restored' => "Restored email configuration: {$this->name}",
            default => "{$eventName} email configuration: {$this->name}",
        };
    }
}
