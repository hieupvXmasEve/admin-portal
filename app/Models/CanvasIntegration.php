<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class CanvasIntegration extends AuditableModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'canvas_url',
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'is_active',
        'last_sync_at',
        'sync_status',
        'sync_error',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'campus_id' => ['required', 'exists:campuses,id'],
            'canvas_url' => ['required', 'url', 'max:255'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    // Relationships
    public function courseMappings(): HasMany
    {
        return $this->hasMany(CanvasCourseMapping::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors & Mutators
    public function setClientSecretAttribute(?string $value): void
    {
        $this->attributes['client_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getClientSecretAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Helper Methods
    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    public function hasValidToken(): bool
    {
        return $this->access_token && ! $this->isTokenExpired();
    }

    /**
     * Check if we can refresh the token automatically
     */
    public function canRefreshToken(): bool
    {
        return ! is_null($this->refresh_token);
    }

    /**
     * Check if integration is usable (has token or can refresh)
     */
    public function isUsable(): bool
    {
        // Has valid token OR has refresh token to get a new one
        return $this->hasValidToken() || $this->canRefreshToken();
    }

    public function markSyncInProgress(): void
    {
        $this->update([
            'sync_status' => 'syncing',
            'sync_error' => null,
        ]);
    }

    public function markSyncCompleted(): void
    {
        $this->update([
            'sync_status' => 'completed',
            'last_sync_at' => now(),
            'sync_error' => null,
        ]);
    }

    public function markSyncFailed(string $error): void
    {
        $this->update([
            'sync_status' => 'failed',
            'sync_error' => $error,
        ]);
    }

    // Logging
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    protected function getStandardLogFields(): array
    {
        return [
            'campus_id',
            'canvas_url',
            'client_id',
            'is_active',
            'sync_status',
        ];
    }

    protected function getIdentifierForLog(): string
    {
        $campus = $this->campus?->name ?? 'Unknown Campus';

        return "Canvas Integration for {$campus}";
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created {$identifier}",
            'updated' => "Updated {$identifier}",
            'deleted' => "Deleted {$identifier}",
            'restored' => "Restored {$identifier}",
            default => "{$eventName} {$identifier}",
        };
    }

    protected function getCustomLogProperties(): array
    {
        return [
            'campus_id' => $this->campus_id,
            'canvas_url' => $this->canvas_url,
            'is_active' => $this->is_active,
            'sync_status' => $this->sync_status,
            'has_token' => ! is_null($this->access_token),
        ];
    }
}
