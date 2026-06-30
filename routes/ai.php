<?php

declare(strict_types=1);

use App\Mcp\Servers\SwinxMcpServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| Loaded by Laravel\Mcp under the `mcp` prefix, so the server below is reached
| at POST /mcp/swinx. It is gated solely by the `api` (Passport) guard — the
| per-user OAuth impersonation boundary (ADR-0010). The unauthenticated OAuth
| discovery (.well-known) + dynamic client registration routes live at the
| application root in routes/web.php (they must NOT inherit the /mcp prefix).
|
*/

Mcp::web('mcp/swinx', SwinxMcpServer::class)
    ->middleware('auth:api');
