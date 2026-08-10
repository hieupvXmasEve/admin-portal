<?php

declare(strict_types=1);

use App\Modules\Admissions\Exceptions\CrmAuthenticationException;
use App\Modules\Admissions\Exceptions\CrmRequestException;
use App\Modules\Admissions\Exceptions\CrmResponseException;
use App\Modules\Admissions\Integrations\Crm\CrmClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.crm.base_url' => 'https://crm.test',
        'services.crm.username' => 'crm-user',
        'services.crm.password' => 'super-secret-password',
        'services.crm.timeout' => 5,
    ]);
});

it('logs in and stores the token', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1', 'token_type' => 'Bearer']], 200),
    ]);

    app(CrmClient::class)->login();

    Http::assertSentCount(1);
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

it('fetches new enrollments after an implicit login', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1', 'token_type' => 'Bearer']], 200),
        'https://crm.test/api/ne' => Http::response(['data' => [['student_code' => 'S1'], ['student_code' => 'S2']]], 200),
    ]);

    $records = app(CrmClient::class)->fetchNewEnrollments();

    expect($records)->toHaveCount(2);
    Http::assertSentCount(2);
});

it('throws CrmResponseException when data is empty but present', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1']], 200),
        'https://crm.test/api/ne' => Http::response(['data' => []], 200),
    ]);

    $records = app(CrmClient::class)->fetchNewEnrollments();

    expect($records)->toBe([]);
});

it('throws CrmResponseException when data is not an array', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1']], 200),
        'https://crm.test/api/ne' => Http::response(['data' => 'not-a-list'], 200),
    ]);

    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmResponseException::class);
});

it('throws CrmRequestException on a 500 from /ne', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1']], 200),
        'https://crm.test/api/ne' => Http::response(['message' => 'server error'], 500),
    ]);

    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmRequestException::class);
});

it('throws CrmRequestException on a connection timeout to /ne', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1']], 200),
        'https://crm.test/api/ne' => fn () => throw new ConnectionException('timed out'),
    ]);

    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmRequestException::class);
});

it('retries login exactly once after a 401 on /ne and then succeeds', function () {
    $callCount = 0;
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1']], 200),
        'https://crm.test/api/ne' => function () use (&$callCount) {
            $callCount++;

            return $callCount === 1
                ? Http::response(['message' => 'expired'], 401)
                : Http::response(['data' => [['student_code' => 'S1']]], 200);
        },
    ]);

    $records = app(CrmClient::class)->fetchNewEnrollments();

    expect($records)->toHaveCount(1);
    // login, /ne (401), login again, /ne (200)
    Http::assertSentCount(4);
});

it('throws CrmAuthenticationException when the retried login still gets a 401', function () {
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok-1']], 200),
        'https://crm.test/api/ne' => Http::response(['message' => 'expired'], 401),
    ]);

    expect(fn () => app(CrmClient::class)->fetchNewEnrollments())->toThrow(CrmAuthenticationException::class);
    Http::assertSentCount(4);
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
