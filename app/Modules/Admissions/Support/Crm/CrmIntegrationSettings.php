<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support\Crm;

use App\Modules\Admissions\Models\CrmIntegrationSetting;

/**
 * Sole read/write surface for the CRM connection config. The DB row (staff
 * editable, no deploy needed) wins; any field left blank there falls back to
 * `config('services.crm.*')` (`.env`) so an environment that has not been
 * configured through the UI yet keeps working.
 */
final class CrmIntegrationSettings
{
    /** @return array{base_url: string, username: string, password: string, timeout: int} */
    public function resolve(): array
    {
        $row = CrmIntegrationSetting::query()->find(CrmIntegrationSetting::ROW_ID);

        return [
            'base_url' => (string) (($row?->base_url ?: null) ?? config('services.crm.base_url')),
            'username' => (string) (($row?->username ?: null) ?? config('services.crm.username')),
            'password' => (string) (($row?->password ?: null) ?? config('services.crm.password')),
            'timeout' => (int) ($row?->timeout ?: config('services.crm.timeout', 120)),
        ];
    }

    /** @return array{base_url: string|null, username: string|null, has_password: bool, timeout: int} for the UI — never exposes the password value */
    public function forDisplay(): array
    {
        $row = CrmIntegrationSetting::query()->find(CrmIntegrationSetting::ROW_ID);

        return [
            'base_url' => $row?->base_url,
            'username' => $row?->username,
            'has_password' => $row?->hasPassword() ?? false,
            'timeout' => $row?->timeout ?? (int) config('services.crm.timeout', 120),
        ];
    }

    /**
     * @param  array{base_url: string, username: string, password: string|null, timeout: int}  $data
     */
    public function save(array $data): void
    {
        $attributes = [
            'base_url' => $data['base_url'],
            'username' => $data['username'],
            'timeout' => $data['timeout'],
        ];

        // A blank password in the form means "keep the current one" — the UI
        // never round-trips the real value, so an empty submit must not wipe it.
        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        // Not updateOrCreate(['id' => ROW_ID], ...): `id` isn't fillable, so
        // mass-assigning it into a freshly-`new`'d model on the create path
        // would be silently dropped and insert an autoincrement row instead
        // of row 1. Setting the key directly bypasses the $fillable guard.
        $row = CrmIntegrationSetting::query()->find(CrmIntegrationSetting::ROW_ID) ?? new CrmIntegrationSetting;
        $row->id = CrmIntegrationSetting::ROW_ID;
        $row->fill($attributes);
        $row->save();
    }
}
