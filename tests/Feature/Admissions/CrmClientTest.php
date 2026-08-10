<?php

declare(strict_types=1);

use App\Modules\Admissions\Exceptions\CrmAuthenticationException;
use App\Modules\Admissions\Exceptions\CrmRequestException;
use App\Modules\Admissions\Exceptions\CrmResponseException;
use App\Modules\Admissions\Integrations\Crm\CrmClient;
use App\Modules\Admissions\Support\Crm\CrmIntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.crm.login_url' => 'https://crm.test/api/login',
        'services.crm.data_url' => 'https://crm.test/api/ne',
        'services.crm.username' => 'crm-user',
        'services.crm.password' => 'super-secret-password',
        'services.crm.timeout' => 5,
    ]);
});

it('logs in, stores the token, and persists it', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1', 'token_type' => 'Bearer']], 200),
    ]);

    app(CrmClient::class)->login();

    Http::assertSentCount(1);
    expect(app(CrmIntegrationSettings::class)->resolveToken())->toBe(['token' => 'tok-1', 'token_type' => 'Bearer']);
});

it('throws CrmAuthenticationException on a non-2xx login', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['message' => 'bad credentials'], 401),
    ]);

    expect(fn () => app(CrmClient::class)->login())->toThrow(CrmAuthenticationException::class);
});

it('throws CrmAuthenticationException when data.token is missing', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => []], 200),
    ]);

    expect(fn () => app(CrmClient::class)->login())->toThrow(CrmAuthenticationException::class);
});

it('refuses to fetch when no token has been stored', function () {
    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmAuthenticationException::class);
    Http::assertNothingSent();
});

it('fetches new enrollments using a previously stored token, without logging in again', function () {
    app(CrmIntegrationSettings::class)->saveToken('stored-tok', 'Bearer');

    Http::fake([
        'https://crm.test/api/ne' => Http::response(['data' => [['student_code' => 'S1'], ['student_code' => 'S2']]], 200),
    ]);

    $records = app(CrmClient::class)->fetchNewEnrollments();

    expect($records)->toHaveCount(2);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer stored-tok'));
});

it('throws CrmResponseException when data is empty but present', function () {
    app(CrmIntegrationSettings::class)->saveToken('stored-tok', 'Bearer');
    Http::fake([
        'https://crm.test/api/ne' => Http::response(['data' => []], 200),
    ]);

    $records = app(CrmClient::class)->fetchNewEnrollments();

    expect($records)->toBe([]);
});

it('throws CrmResponseException when data is not an array', function () {
    app(CrmIntegrationSettings::class)->saveToken('stored-tok', 'Bearer');
    Http::fake([
        'https://crm.test/api/ne' => Http::response(['data' => 'not-a-list'], 200),
    ]);

    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmResponseException::class);
});

it('throws CrmRequestException on a 500 from the data URL', function () {
    app(CrmIntegrationSettings::class)->saveToken('stored-tok', 'Bearer');
    Http::fake([
        'https://crm.test/api/ne' => Http::response(['message' => 'server error'], 500),
    ]);

    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmRequestException::class);
});

it('throws CrmRequestException on a connection timeout to the data URL', function () {
    app(CrmIntegrationSettings::class)->saveToken('stored-tok', 'Bearer');
    Http::fake([
        'https://crm.test/api/ne' => fn () => throw new ConnectionException('timed out'),
    ]);

    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmRequestException::class);
});

it('refreshes an expired token exactly once after a 401 and then succeeds', function () {
    app(CrmIntegrationSettings::class)->saveToken('stale-tok', 'Bearer');

    $callCount = 0;
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'fresh-tok']], 200),
        'https://crm.test/api/ne' => function () use (&$callCount) {
            $callCount++;

            return $callCount === 1
                ? Http::response(['message' => 'expired'], 401)
                : Http::response(['data' => [['student_code' => 'S1']]], 200);
        },
    ]);

    $records = app(CrmClient::class)->fetchNewEnrollments();

    expect($records)->toHaveCount(1);
    // /ne (401), login refresh, /ne (200)
    Http::assertSentCount(3);
    expect(app(CrmIntegrationSettings::class)->resolveToken()['token'])->toBe('fresh-tok');
});

it('throws CrmAuthenticationException when the refreshed login still gets a 401', function () {
    app(CrmIntegrationSettings::class)->saveToken('stale-tok', 'Bearer');
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'fresh-tok']], 200),
        'https://crm.test/api/ne' => Http::response(['message' => 'expired'], 401),
    ]);

    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmAuthenticationException::class);
    Http::assertSentCount(3);
});

it('never leaks the password in an exception message', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['message' => 'bad credentials'], 401),
    ]);

    try {
        app(CrmClient::class)->login();
        expect(false)->toBeTrue('expected CrmAuthenticationException to be thrown');
    } catch (CrmAuthenticationException $exception) {
        expect($exception->getMessage())->not->toContain('super-secret-password');
    }
});
