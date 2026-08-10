<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Integrations\Crm;

use App\Modules\Admissions\Exceptions\CrmAuthenticationException;
use App\Modules\Admissions\Exceptions\CrmRequestException;
use App\Modules\Admissions\Exceptions\CrmResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Logs in, holds the bearer token for the duration of one run, and fetches
 * `/api/ne`. Validates envelope shape only — record contents are the
 * mapper's job (Phase 3), so one malformed record never fails the whole fetch.
 */
class CrmClient
{
    private string $baseUrl;

    private string $username;

    private string $password;

    private int $timeout;

    private ?string $token = null;

    private ?string $tokenType = null;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.crm.base_url');
        $this->username = (string) config('services.crm.username');
        $this->password = (string) config('services.crm.password');
        $this->timeout = (int) config('services.crm.timeout', 120);
    }

    public function login(): void
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->post('/api/login', [
                    'username' => $this->username,
                    'password' => $this->password,
                ]);
        } catch (ConnectionException) {
            throw new CrmAuthenticationException("CRM login failed: connection error at {$this->baseUrl}/api/login.");
        }

        if ($response->failed()) {
            throw new CrmAuthenticationException("CRM login failed with HTTP {$response->status()} at /api/login.");
        }

        $body = $response->json();
        $token = $body['data']['token'] ?? null;
        $tokenType = $body['data']['token_type'] ?? 'Bearer';

        if (! is_string($token) || $token === '') {
            throw new CrmAuthenticationException('CRM login response is missing data.token.');
        }

        $this->token = $token;
        $this->tokenType = (string) $tokenType;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchNewEnrollments(): array
    {
        if ($this->token === null) {
            $this->login();
        }

        $response = $this->requestNewEnrollments();

        if ($response->status() === 401) {
            $this->token = null;
            $this->login();
            $response = $this->requestNewEnrollments();

            if ($response->status() === 401) {
                throw new CrmAuthenticationException('CRM re-authentication failed after a 401 on /api/ne.');
            }
        }

        if ($response->failed()) {
            throw new CrmRequestException("CRM request failed with HTTP {$response->status()} at /api/ne.");
        }

        $body = $response->json();
        $data = $body['data'] ?? null;

        if (! is_array($data) || ! array_is_list($data)) {
            throw new CrmResponseException('CRM response at /api/ne is missing a `data` list.');
        }

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    private function requestNewEnrollments(): Response
    {
        try {
            return Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->withToken((string) $this->token, (string) $this->tokenType)
                ->get('/api/ne');
        } catch (ConnectionException) {
            throw new CrmRequestException("CRM request failed: connection error or timeout at {$this->baseUrl}/api/ne.");
        }
    }
}
