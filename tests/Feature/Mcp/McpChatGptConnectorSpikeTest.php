<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Client;

uses(RefreshDatabase::class);

// Rate limiters persist hit counters in the shared container cache store; flush so the
// per-IP DCR/OAuth throttles do not carry over from other Mcp tests.
beforeEach(fn () => Cache::flush());

/**
 * Issue 07 — ChatGPT connector spike (go/no-go), driven through the real HTTP boundary.
 *
 * These assertions LOCK the OAuth 2.0 discovery surface a ChatGPT custom MCP connector
 * requires to bootstrap from the bare `/mcp/swinx` URL. The same surface is
 * consumed by other supported MCP clients, so this protects the shared connector
 * contract documented in docs/features/ai/mcp.md.
 *
 * The outcome the spike recorded is TECHNICAL-GO: every requirement below is already
 * satisfied by the pinned laravel/mcp build. Client enablement remains an
 * operational/data-handling decision rather than a connector implementation gap.
 */
it('serves path-suffixed protected-resource metadata (RFC 9728) naming the mcp resource', function () {
    // ChatGPT fetches the resource-suffixed PRM discovered from the 401 WWW-Authenticate
    // header, not just the bare well-known path. It must name the concrete resource URL.
    $response = $this->getJson('/.well-known/oauth-protected-resource/mcp/swinx');

    $response->assertOk()
        ->assertJsonStructure(['resource', 'authorization_servers', 'scopes_supported']);

    expect($response->json('resource'))->toContain('/mcp/swinx')
        ->and($response->json('authorization_servers'))->toBeArray()->not->toBeEmpty()
        ->and($response->json('scopes_supported'))->toContain('mcp:use');
});

it('challenges an unauthenticated tool call with WWW-Authenticate pointing at resource metadata', function () {
    // RFC 9728 §5.1 / MCP authorization spec: the 401 must carry a WWW-Authenticate header
    // whose resource_metadata parameter lets a client (ChatGPT) discover the authorization
    // server from the MCP URL alone. Without it, a paste-the-URL connector cannot bootstrap.
    $response = $this->postJson('/mcp/swinx', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertUnauthorized();

    $challenge = $response->headers->get('WWW-Authenticate');

    expect($challenge)->not->toBeNull()
        ->and($challenge)->toContain('Bearer')
        ->and($challenge)->toContain('resource_metadata=')
        ->and($challenge)->toContain('/.well-known/oauth-protected-resource/mcp/swinx');
});

it('advertises PKCE, refresh, and the mcp scope in authorization-server metadata', function () {
    // ChatGPT requires PKCE (S256) and uses refresh tokens for long-lived connectors.
    $response = $this->getJson('/.well-known/oauth-authorization-server');

    $response->assertOk();

    expect($response->json('code_challenge_methods_supported'))->toContain('S256')
        ->and($response->json('grant_types_supported'))->toContain('authorization_code')
        ->and($response->json('grant_types_supported'))->toContain('refresh_token')
        ->and($response->json('scopes_supported'))->toContain('mcp:use')
        ->and($response->json('registration_endpoint'))->toContain('oauth/register');
});

it('registers a ChatGPT redirect URI as a public PKCE client via dynamic client registration', function () {
    // ChatGPT self-registers (RFC 7591) with its hosted redirect URI and expects a public
    // client (no secret): token_endpoint_auth_method=none, the mcp scope, and the code grant.
    $response = $this->postJson('/oauth/register', [
        'client_name' => 'ChatGPT',
        'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
    ]);

    $response->assertCreated();

    expect($response->json('client_id'))->not->toBeNull()
        ->and($response->json('token_endpoint_auth_method'))->toBe('none')
        ->and($response->json('scope'))->toContain('mcp:use')
        ->and($response->json('grant_types'))->toContain('authorization_code')
        ->and($response->json('response_types'))->toContain('code');
});

it('renders consent without rejecting the RFC 8707 resource indicator ChatGPT sends', function () {
    // ChatGPT appends a `resource` parameter (RFC 8707) to the authorize request. Passport
    // must tolerate it (render consent), even though v1 does not audience-bind the token —
    // the accepted residual documented in docs/features/ai/mcp.md, matching
    // the other supported MCP client paths.
    $user = User::factory()->create();

    $client = (new Client)->forceFill([
        'name' => 'ChatGPT',
        'secret' => null,
        'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
        'grant_types' => ['authorization_code'],
        'revoked' => false,
    ]);
    $client->save();

    $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://chatgpt.com/connector_platform_oauth_redirect',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
        'state' => 'xyz',
        'resource' => 'http://localhost/mcp/swinx',
    ]));

    $response->assertOk()
        ->assertSee('Authorize connection')
        ->assertSee('Third-party application');
});
