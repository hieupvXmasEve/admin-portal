<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

// The rate limiters persist their hit counters in the cache store, which is shared across
// test runs in the container; flush it so each throttle assertion starts from a clean slate.
beforeEach(fn () => Cache::flush());

/**
 * Public-exposure hardening (issue 06 / ADR-0011), driven through the real HTTP boundary:
 * Host/Origin allowlist, tool-argument ceiling, per-user tool-call throttle, and the
 * per-IP OAuth/DCR throttle. These run in front of the OAuth guard, so most need no token.
 */
function mcpToolsListBody(): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
}

it('rejects a cross-origin request with a disallowed Origin header', function () {
    $this->postJson('/mcp/swinx', mcpToolsListBody(), ['Origin' => 'https://evil.example.com'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', -32600);
});

it('rejects a request whose Host header is not on the allowlist (DNS-rebinding guard)', function () {
    // The host lives in the request URL: SymfonyRequest::create() derives HTTP_HOST from it,
    // which is exactly what getHost() (and so the DNS-rebinding guard) reads.
    $this->postJson('http://attacker.example.com/mcp/swinx', mcpToolsListBody())
        ->assertStatus(403);
});

it('allows the configured application host and a matching Origin', function () {
    // No Origin (a native agent client) on the default localhost Host passes origin validation
    // and falls through to the OAuth guard — proving the guard does not block legitimate hosts.
    $this->postJson('/mcp/swinx', mcpToolsListBody())
        ->assertUnauthorized();
});

it('rejects tool arguments that exceed the maximum permitted size', function () {
    config()->set('mcp.max_argument_bytes', 1024);

    $oversized = str_repeat('x', 2048);

    $this->postJson('/mcp/swinx', [
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'tools/call',
        'params' => ['name' => 'query_metrics', 'arguments' => ['blob' => $oversized]],
    ])->assertStatus(413)
        ->assertJsonPath('error.code', -32600);
});

it('returns 429 once a user exceeds the per-user tool-call rate limit', function () {
    config()->set('mcp.rate_limits.tool_calls_per_minute', 2);

    $user = User::factory()->create();

    // Two calls are within budget (status is not the throttle 429)...
    foreach (range(1, 2) as $attempt) {
        expect($this->actingAs($user, 'api')->postJson('/mcp/swinx', mcpToolsListBody())->getStatusCode())
            ->not->toBe(429);
    }

    // ...the third trips the limiter.
    $this->actingAs($user, 'api')->postJson('/mcp/swinx', mcpToolsListBody())
        ->assertStatus(429);
});

it('rate-limits the OAuth discovery endpoints per IP', function () {
    config()->set('mcp.rate_limits.oauth_per_minute', 2);

    $this->getJson('/.well-known/oauth-authorization-server')->assertOk();
    $this->getJson('/.well-known/oauth-authorization-server')->assertOk();

    $this->getJson('/.well-known/oauth-authorization-server')->assertStatus(429);
});

it('rate-limits the dynamic client registration endpoint per IP', function () {
    config()->set('mcp.rate_limits.registration_per_hour', 2);

    $payload = [
        'client_name' => 'Throttle Probe',
        'redirect_uris' => ['https://client.example.com/callback'],
    ];

    $this->postJson('/oauth/register', $payload)->assertSuccessful();
    $this->postJson('/oauth/register', $payload)->assertSuccessful();

    $this->postJson('/oauth/register', $payload)->assertStatus(429);
});
