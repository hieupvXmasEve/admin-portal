<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Read-only tracer-bullet tool for the OAuth + MCP skeleton spike (Issue 01).
 *
 * Proves a real MCP client can complete the OAuth round-trip and call a tool. It is
 * replaced by the three real tools in a later slice. The base class would auto-derive
 * the name `ping-tool` from the class name, so the canonical name `ping` is pinned via
 * the `$name` property.
 */
#[IsReadOnly]
#[Title('Ping')]
class PingTool extends Tool
{
    protected string $name = 'ping';

    protected string $description = 'Health-check tool that echoes back a pong; confirms the connection and OAuth session are working.';

    public function handle(Request $request): Response
    {
        return Response::text('pong');
    }
}
