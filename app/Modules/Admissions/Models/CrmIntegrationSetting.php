<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single-row (id=1) CRM connection config. `password` is encrypted at
 * rest (Laravel's `encrypted` cast) — never logged, never rendered back to
 * the staff UI in plaintext (see {@see self::hasPassword()}).
 */
class CrmIntegrationSetting extends Model
{
    public const ROW_ID = 1;

    protected $fillable = [
        'base_url',
        'username',
        'password',
        'timeout',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'timeout' => 'integer',
        ];
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }
}
