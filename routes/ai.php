<?php

declare(strict_types=1);

use App\Http\Middleware\LimitMcpToolArguments;
use App\Http\Middleware\ValidateMcpOrigin;
use App\Mcp\Servers\SwinxMcpServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| Loaded by Laravel\Mcp under the `mcp` prefix, so the server below is reached
| at POST /mcp/swinx. The public-exposure wrap (ADR-0011) is attached HERE at
| registration, never on the global stack, so the rest of the app is untouched:
|
|   - ValidateMcpOrigin     — Host/Origin allowlist (DNS-rebinding guard) and
|   - LimitMcpToolArguments — caps oversized tool-argument payloads,
|     both before auth so a hostile probe is rejected without auth work;
|   - auth:api              — the per-user OAuth impersonation boundary (ADR-0010),
|     the sole access gate (no anonymous access);
|   - throttle:mcp          — per-user tool-call rate limit (runs after auth so the
|     key is the resolved OAuth actor).
|
| The unauthenticated OAuth discovery (.well-known) + dynamic client registration
| routes live at the application root in routes/web.php (they must NOT inherit the
| /mcp prefix) and carry their own per-IP throttle.
|
*/

Mcp::web('mcp/swinx', SwinxMcpServer::class)
    ->middleware([
        ValidateMcpOrigin::class,
        LimitMcpToolArguments::class,
        'auth:api',
        'throttle:mcp',
    ]);
