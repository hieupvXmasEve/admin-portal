<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;

final class LecturerAccessEligibilityHandoff extends Model
{
    protected $fillable = [
        'deduplication_key',
        'lecturer_id',
        'user_id',
        'eligibility_status',
        'reason',
        'revoked_token_count',
        'payload',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'applied_at' => 'datetime',
        ];
    }
}
