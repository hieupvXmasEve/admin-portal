<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Client;

uses(RefreshDatabase::class);

it('keeps the web and student guards resolvable while adding the api guard', function () {
    // Regression: the default guard is unchanged and the existing guards still resolve.
    expect(config('auth.defaults.guard'))->toBe('web')
        ->and(Auth::guard('web'))->toBeInstanceOf(Guard::class)
        ->and(Auth::guard('student'))->toBeInstanceOf(Guard::class)
        ->and(Auth::guard('api'))->toBeInstanceOf(Guard::class);

    // The api guard is the Passport OAuth guard and starts unauthenticated.
    expect(Auth::guard('api')->check())->toBeFalse()
        ->and(config('auth.guards.api.driver'))->toBe('passport')
        ->and(config('auth.guards.api.provider'))->toBe('users');
});

it('advertises OAuth discovery metadata with authorize, token and registration endpoints', function () {
    $response = $this->getJson('/.well-known/oauth-authorization-server');

    $response->assertOk()
        ->assertJsonStructure([
            'issuer',
            'authorization_endpoint',
            'token_endpoint',
            'registration_endpoint',
            'response_types_supported',
            'code_challenge_methods_supported',
            'grant_types_supported',
        ]);

    expect($response->json('registration_endpoint'))->toContain('oauth/register')
        ->and($response->json('code_challenge_methods_supported'))->toContain('S256');

    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonStructure(['resource', 'authorization_servers']);
});

it('returns a client id from the dynamic client registration endpoint (not a 419)', function () {
    $response = $this->postJson('/oauth/register', [
        'client_name' => 'Test MCP Client',
        'redirect_uris' => ['https://client.example.com/callback'],
    ]);

    $response->assertSuccessful();

    expect($response->json('client_id'))->not->toBeNull();
});

it('renders the branded consent at /oauth/authorize without forcing campus selection', function () {
    // Regression: the campus-selection web middleware must not bounce the OAuth consent
    // route, even when the signed-in staff member has no campus selected in session.
    $user = User::factory()->create();

    $client = (new Client)->forceFill([
        'name' => 'Inspector',
        'secret' => null,
        'redirect_uris' => ['http://localhost:6274/oauth/callback'],
        'grant_types' => ['authorization_code'],
        'revoked' => false,
    ]);
    $client->save();

    $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'http://localhost:6274/oauth/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
        'state' => 'xyz',
    ]));

    $response->assertOk()
        ->assertSee('Authorize connection')
        ->assertSee('Third-party application')
        ->assertSee('Inspector');
});

it('refuses an unauthenticated MCP tool request', function () {
    // No Passport token → the api guard rejects the call (no anonymous access, ADR-0011).
    $this->postJson('/mcp/swinx', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'query_metrics', 'arguments' => []],
    ])->assertUnauthorized();
});
