<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Database\Factories\Modules\Identity\Models\GuardianAccessGrantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class GuardianAccessGrant extends Model
{
    /** @use HasFactory<GuardianAccessGrantFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'guardian_relationship_id',
        'parent_id',
        'student_id',
        'access_level',
        'status',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
