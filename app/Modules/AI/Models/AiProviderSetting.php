<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProviderSetting extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'default_model',
        'encrypted_api_key',
        'enabled',
        'daily_limit_cents',
        'monthly_limit_cents',
        'tested_at',
        'last_test_status',
        'last_test_error_code',
        'last_used_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_api_key' => 'encrypted',
            'enabled' => 'boolean',
            'daily_limit_cents' => 'integer',
            'monthly_limit_cents' => 'integer',
            'tested_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function hasApiKey(): bool
    {
        return filled($this->encrypted_api_key);
    }

    public function apiKeyMask(): ?string
    {
        if (! $this->hasApiKey()) {
            return null;
        }

        $apiKey = (string) $this->encrypted_api_key;

        if (mb_strlen($apiKey) <= 6) {
            return str_repeat('*', mb_strlen($apiKey));
        }

        return mb_substr($apiKey, 0, 2).'...'.mb_substr($apiKey, -4);
    }
}
