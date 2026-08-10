<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Integrations\Crm;

use App\Modules\Admissions\Exceptions\CrmAuthenticationException;
use App\Modules\Admissions\Exceptions\CrmRequestException;
use App\Modules\Admissions\Exceptions\CrmResponseException;
use App\Modules\Admissions\Support\Crm\CrmIntegrationSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Logs in and fetches New Enrollment records. Validates envelope shape
 * only — record contents are the mapper's job (Phase 3), so one malformed
 * record never fails the whole fetch.
 *
 * `login_url`/`data_url` are full, staff-supplied endpoint URLs — never a
 * host with a hardcoded `/api/login` or `/api/ne` path appended, since the
 * CRM's own routing is not this codebase's to assume.
 *
 * Login is an explicit, one-time staff action (Admissions UI addendum,
 * 2026-08-10): the bearer token persists in {@see CrmIntegrationSettings}
 * across requests, and {@see self::fetchNewEnrollments()} requires an
 * existing stored token rather than logging in implicitly. A 401 still
 * triggers one automatic *refresh* of an already-held token — that is
 * renewing a session, not the initial login the UI now gates explicitly.
 */
class CrmClient
{
    private string $loginUrl;

    private string $dataUrl;

    private string $username;

    private string $password;

    private int $timeout;

    private ?string $token;

    private ?string $tokenType;

    public function __construct(private readonly CrmIntegrationSettings $settings)
    {
        $resolved = $settings->resolve();
        $this->loginUrl = $resolved['login_url'];
        $this->dataUrl = $resolved['data_url'];
        $this->username = $resolved['username'];
        $this->password = $resolved['password'];
        $this->timeout = $resolved['timeout'];

        $storedToken = $settings->resolveToken();
        $this->token = $storedToken['token'] ?? null;
        $this->tokenType = $storedToken['token_type'] ?? null;
    }

    public function login(): void
    {
        try {
            $response = Http::timeout($this->timeout)->post($this->loginUrl, [
                'username' => $this->username,
                'password' => $this->password,
            ]);
        } catch (ConnectionException) {
            throw new CrmAuthenticationException("CRM login failed: connection error at {$this->loginUrl}.");
        }

        if ($response->failed()) {
            throw new CrmAuthenticationException("CRM login failed with HTTP {$response->status()} at the login URL.");
        }

        $body = $response->json();
        $token = $body['data']['token'] ?? null;
        $tokenType = $body['data']['token_type'] ?? 'Bearer';

        if (! is_string($token) || $token === '') {
            throw new CrmAuthenticationException('CRM login response is missing data.token.');
        }

        $this->token = $token;
        $this->tokenType = (string) $tokenType;
        $this->settings->saveToken($token, (string) $tokenType);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchNewEnrollments(): array
    {
        if ($this->token === null) {
            throw new CrmAuthenticationException('No CRM token stored. Log in before syncing.');
        }

        $response = $this->requestNewEnrollments();

        if ($response->status() === 401) {
            $this->login();
            $response = $this->requestNewEnrollments();

            if ($response->status() === 401) {
                throw new CrmAuthenticationException('CRM re-authentication failed after a 401 on the data URL.');
            }
        }

        if ($response->failed()) {
            throw new CrmRequestException("CRM request failed with HTTP {$response->status()} at the data URL.");
        }

        $body = $response->json();
        $data = $body['data'] ?? null;

        if (! is_array($data) || ! array_is_list($data)) {
            throw new CrmResponseException('CRM response at the data URL is missing a `data` list.');
        }

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    private function requestNewEnrollments(): Response
    {
        try {
            return Http::timeout($this->timeout)
                ->withToken((string) $this->token, (string) $this->tokenType)
                ->get($this->dataUrl);
        } catch (ConnectionException) {
            throw new CrmRequestException("CRM request failed: connection error or timeout at {$this->dataUrl}.");
        }
    }
}
