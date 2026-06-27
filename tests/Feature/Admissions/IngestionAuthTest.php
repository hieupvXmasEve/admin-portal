<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Admissions\AdmissionsServiceAccountManager;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The ingestion throttle uses the cache store; clear it so per-test hit
    // counts do not leak across tests.
    Cache::flush();
});

function pingUrl(): string
{
    return route('v1.admissions.ping');
}

it('returns pong in the ApiResponse envelope for a token carrying the ingest ability', function () {
    $service = User::factory()->create(['type' => UserType::SERVICE]);
    Sanctum::actingAs($service, [AdmissionsIngestion::ABILITY]);

    $this->getJson(pingUrl())
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'pong',
            'data' => [
                'service' => 'admissions-ingestion',
                'ability' => AdmissionsIngestion::ABILITY,
            ],
        ]);
});

it('rejects a token that lacks the ingest ability with 403', function () {
    $service = User::factory()->create(['type' => UserType::SERVICE]);
    Sanctum::actingAs($service, ['some:other-ability']);

    $this->getJson(pingUrl())
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'errors' => [
                ['code' => 'AUTHORIZATION_ERROR'],
            ],
        ]);
});

it('rejects an unauthenticated request with 401', function () {
    $this->getJson(pingUrl())
        ->assertUnauthorized()
        ->assertJson([
            'success' => false,
            'errors' => [
                ['code' => 'AUTHENTICATION_ERROR'],
            ],
        ]);
});

it('mints a usable ingest token end-to-end via the service account manager', function () {
    $token = app(AdmissionsServiceAccountManager::class)->mintIngestToken();

    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson(pingUrl())
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'pong']);
});

it('creates a service account that cannot authenticate to the web UI', function () {
    $service = User::factory()->create([
        'type' => UserType::SERVICE,
        'email' => 'crm-bot@service.test',
        'password' => 'password',
    ]);

    expect($service->isService())->toBeTrue();

    // The staff-only web login gate rejects the service account.
    session(['_token' => 'login-csrf']);
    $this->withHeader('X-CSRF-TOKEN', 'login-csrf')
        ->post(route('login'), [
            'email' => 'crm-bot@service.test',
            'password' => 'password',
        ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('mints a token scoped to exactly the ingest ability', function () {
    $manager = app(AdmissionsServiceAccountManager::class);
    $token = $manager->mintIngestToken();

    $accessToken = $token->accessToken;
    expect($accessToken->can(AdmissionsIngestion::ABILITY))->toBeTrue();
    expect($accessToken->can('some:other-ability'))->toBeFalse();
    expect($accessToken->tokenable->isService())->toBeTrue();
});

it('reuses the same service account across mints (no duplicate accounts)', function () {
    $manager = app(AdmissionsServiceAccountManager::class);
    $manager->mintIngestToken();
    $manager->mintIngestToken();

    expect(User::where('type', UserType::SERVICE)->count())->toBe(1);
});

it('enforces the configured IP allowlist on the group', function () {
    // An allowlist that excludes the test client IP must block the call.
    config(['admissions.ingest.ip_allowlist' => ['10.99.99.99']]);

    $service = User::factory()->create(['type' => UserType::SERVICE]);
    Sanctum::actingAs($service, [AdmissionsIngestion::ABILITY]);

    $this->getJson(pingUrl())
        ->assertForbidden()
        ->assertJson(['success' => false]);
});

it('applies the rate limiter to the group', function () {
    config(['admissions.ingest.rate_limit.max_attempts' => 1]);

    $service = User::factory()->create(['type' => UserType::SERVICE]);
    Sanctum::actingAs($service, [AdmissionsIngestion::ABILITY]);

    $this->getJson(pingUrl())->assertOk();
    $this->getJson(pingUrl())->assertStatus(429);
});

it('audit-logs each authorized ingestion call', function () {
    $service = User::factory()->create(['type' => UserType::SERVICE]);
    Sanctum::actingAs($service, [AdmissionsIngestion::ABILITY]);

    $this->getJson(pingUrl())->assertOk();

    $entry = DB::table('activity_log')
        ->where('log_name', AdmissionsIngestion::AUDIT_LOG)
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($service->id);

    $properties = json_decode($entry->properties, true);
    expect($properties['path'])->toBe('api/v1/admissions/ping');
    expect($properties['status'])->toBe(200);
});

it('audit-logs an authenticated call that is denied for lacking the ability', function () {
    $service = User::factory()->create(['type' => UserType::SERVICE]);
    Sanctum::actingAs($service, ['some:other-ability']);

    $this->getJson(pingUrl())->assertForbidden();

    $entry = DB::table('activity_log')
        ->where('log_name', AdmissionsIngestion::AUDIT_LOG)
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->causer_id)->toBe($service->id);
    expect(json_decode($entry->properties, true)['status'])->toBe(403);
});
