<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;

final class LecturerAccessGrant extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'lecturer_id',
        'token_subject_type',
        'status',
        'reason',
        'eligibility_evaluated_at',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'eligibility_evaluated_at' => 'datetime',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
