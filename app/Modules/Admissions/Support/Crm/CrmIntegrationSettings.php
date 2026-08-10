<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support\Crm;

use App\Modules\Admissions\Models\CrmIntegrationSetting;

/**
 * Sole read/write surface for the CRM connection config. `login_url` and
 * `data_url` are full, staff-supplied endpoint URLs (never assembled from a
 * host + hardcoded path). Any field left blank on the DB row falls back to
 * `config('services.crm.*')` (`.env`) so an environment not yet configured
 * through the UI keeps working — except the token, which only ever comes
 * from an explicit login (no `.env` fallback for a bearer token).
 */
final class CrmIntegrationSettings
{
    /** @return array{login_url: string, data_url: string, username: string, password: string, timeout: int} */
    public function resolve(): array
    {
        $row = $this->row();

        return [
            'login_url' => (string) (($row?->login_url ?: null) ?? config('services.crm.login_url')),
            'data_url' => (string) (($row?->data_url ?: null) ?? config('services.crm.data_url')),
            'username' => (string) (($row?->username ?: null) ?? config('services.crm.username')),
            'password' => (string) (($row?->password ?: null) ?? config('services.crm.password')),
            'timeout' => (int) ($row?->timeout ?: config('services.crm.timeout', 120)),
        ];
    }

    /** @return array{token: string, token_type: string}|null */
    public function resolveToken(): ?array
    {
        $row = $this->row();

        if ($row === null || ! $row->hasToken()) {
            return null;
        }

        return ['token' => (string) $row->token, 'token_type' => (string) ($row->token_type ?: 'Bearer')];
    }

    /**
     * @return array{login_url: string|null, data_url: string|null, username: string|null, has_password: bool, timeout: int, logged_in: bool, token_obtained_at: string|null} for the UI — never exposes password or token values
     */
    public function forDisplay(): array
    {
        $row = $this->row();

        return [
            'login_url' => $row?->login_url,
            'data_url' => $row?->data_url,
            'username' => $row?->username,
            'has_password' => $row?->hasPassword() ?? false,
            'timeout' => $row?->timeout ?? (int) config('services.crm.timeout', 120),
            'logged_in' => $row?->hasToken() ?? false,
            'token_obtained_at' => $row?->token_obtained_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{login_url: string, data_url: string, username: string, password: string|null, timeout: int}  $data
     */
    public function save(array $data): void
    {
        $attributes = [
            'login_url' => $data['login_url'],
            'data_url' => $data['data_url'],
            'username' => $data['username'],
            'timeout' => $data['timeout'],
        ];

        // A blank password in the form means "keep the current one" — the UI
        // never round-trips the real value, so an empty submit must not wipe it.
        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        $this->fill($attributes);
    }

    public function saveToken(string $token, string $tokenType): void
    {
        $this->fill(['token' => $token, 'token_type' => $tokenType, 'token_obtained_at' => now()]);
    }

    private function row(): ?CrmIntegrationSetting
    {
        return CrmIntegrationSetting::query()->find(CrmIntegrationSetting::ROW_ID);
    }

    /** @param array<string, mixed> $attributes */
    private function fill(array $attributes): void
    {
        // Not updateOrCreate(['id' => ROW_ID], ...): `id` isn't fillable, so
        // mass-assigning it into a freshly-`new`'d model on the create path
        // would be silently dropped and insert an autoincrement row instead
        // of row 1. Setting the key directly bypasses the $fillable guard.
        $row = $this->row() ?? new CrmIntegrationSetting;
        $row->id = CrmIntegrationSetting::ROW_ID;
        $row->fill($attributes);
        $row->save();
    }
}
