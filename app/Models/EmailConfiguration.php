<?php

namespace App\Models;

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
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Encrypt the password when setting it
     */
    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value !== null ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt the password when getting it
     */
    public function getPasswordAttribute(?string $value): ?string
    {
        if ($value !== null) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
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
        ];
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
