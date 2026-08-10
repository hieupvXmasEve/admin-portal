<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single-row (id=1) CRM connection config. `login_url`/`data_url` are
 * full, staff-supplied endpoint URLs — {@see \App\Modules\Admissions\Integrations\Crm\CrmClient}
 * never assumes or concatenates a path onto them. `password` and `token` are
 * encrypted at rest (Laravel's `encrypted` cast) — never logged, never
 * rendered back to the staff UI in plaintext.
 */
class CrmIntegrationSetting extends Model
{
    public const ROW_ID = 1;

    protected $fillable = [
        'login_url',
        'data_url',
        'username',
        'password',
        'timeout',
        'token',
        'token_type',
        'token_obtained_at',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'token' => 'encrypted',
            'timeout' => 'integer',
            'token_obtained_at' => 'datetime',
        ];
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function hasToken(): bool
    {
        return filled($this->token);
    }
}
