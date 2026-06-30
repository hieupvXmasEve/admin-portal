<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Controlled MCP server hardening
|--------------------------------------------------------------------------
|
| The MCP endpoint is reachable from the public internet (ADR-0011), so the
| host/origin allowlist, tool-argument ceiling, and per-user / per-IP rate
| limits below are the abuse boundary in front of the OAuth guard. Every value
| is env-overridable so it can be tightened per environment without a deploy.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | OAuth client redirect policy (laravel/mcp keys)
    |--------------------------------------------------------------------------
    |
    | These keys are read by the package's OAuthRegisterController (dynamic client
    | registration) and discovery metadata. They are restated here — rather than
    | left to the package's merged defaults — so the public DCR redirect policy is
    | explicit and reviewable in one place (the price of a public, pre-1.0 endpoint).
    |
    | Per ADR-0011 the DCR endpoint stays OPEN (`*`) so external clients self-register
    | with zero install; the Swinx-branded consent screen — not a closed redirect
    | allowlist — is the anti-phishing control. Tighten `redirect_domains` to explicit
    | scheme+host entries to harden registration further without code changes.
    |
    */
    'redirect_domains' => [
        '*',
    ],

    // Native desktop clients (Cursor, VS Code) use private-use URI schemes (RFC 8252)
    // for redirect callbacks. List any allowed custom schemes here. Empty = none.
    'custom_schemes' => [],

    // OAuth authorization-server issuer (RFC 8414) advertised in discovery metadata.
    // Null defaults to url('/').
    'authorization_server' => env('MCP_AUTHORIZATION_SERVER'),

    // Extra Origin header values (scheme + host) accepted by ValidateMcpOrigin,
    // on top of the application URL and the local-development hosts. Comma list.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_ALLOWED_ORIGINS', ''))
    ))),

    // Extra Host header values accepted by ValidateMcpOrigin (DNS-rebinding guard),
    // on top of the application URL host and the local-development hosts. Comma list.
    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MCP_ALLOWED_HOSTS', ''))
    ))),

    // Maximum JSON-encoded byte length of a single tool call's `arguments` object.
    // Caps prompt-injection / memory-pressure payloads before they reach a tool.
    'max_argument_bytes' => (int) env('MCP_MAX_ARGUMENT_BYTES', 8192),

    'rate_limits' => [
        // Tool calls are limited per acting user — the abuse boundary is a person,
        // and a per-client limit could be evaded by registering many clients.
        'tool_calls_per_minute' => (int) env('MCP_TOOL_CALLS_PER_MINUTE', 60),

        // OAuth discovery/authorize endpoints are limited per IP.
        'oauth_per_minute' => (int) env('MCP_OAUTH_PER_MINUTE', 10),

        // Dynamic client registration is limited per IP, far tighter, because an
        // open registration endpoint is the registration-spam abuse target.
        'registration_per_hour' => (int) env('MCP_REGISTRATION_PER_HOUR', 5),
    ],
];
