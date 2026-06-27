<?php

declare(strict_types=1);

namespace App\Services\Admissions;

use App\Models\User;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use App\Shared\Support\Enums\UserType;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

/**
 * Owns the dedicated admissions-CRM service account and the minting of its
 * scoped ingestion tokens (ADR-0004).
 *
 * The account is a {@see UserType::SERVICE} User that can never log into the web
 * UI (the staff-only web login gate rejects non-staff). It carries an
 * unguessable random password purely so no credential path exists; it acts only
 * through Sanctum tokens scoped to {@see AdmissionsIngestion::ABILITY}.
 */
class AdmissionsServiceAccountManager
{
    /**
     * Find the admissions service account, creating it on first use.
     */
    public function resolveAccount(): User
    {
        $config = config('admissions.ingest.service_account');

        return User::firstOrCreate(
            ['email' => $config['email']],
            [
                'name' => $config['name'],
                'type' => UserType::SERVICE,
                'status' => User::STATUS_ACTIVE,
                // Random, never shared — the account has no usable credentials.
                'password' => Str::random(64),
            ],
        );
    }

    /**
     * Mint a fresh ingestion-scoped Sanctum token for the service account.
     *
     * Returns the {@see NewAccessToken} so the caller can surface the one-time
     * plaintext token; only the hash is persisted.
     */
    public function mintIngestToken(): NewAccessToken
    {
        return $this->resolveAccount()->createToken(
            AdmissionsIngestion::TOKEN_NAME,
            [AdmissionsIngestion::ABILITY],
        );
    }
}
