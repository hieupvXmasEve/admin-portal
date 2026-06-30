<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\PingTool;
use Laravel\Mcp\Server;

/**
 * Controlled MCP server (ADR-0009) exposing Swinx's read-only AI tools to external
 * agent clients under per-user OAuth impersonation.
 *
 * Step 1 (spike) registers only the read-only `ping` tracer tool; the three real
 * tools (`query_metrics`, `search_entities`, `get_entity_profile`) are mapped in a
 * later slice. The server is reached at `POST /mcp/swinx` behind the `api` (Passport)
 * guard — see routes/ai.php.
 */
class SwinxMcpServer extends Server
{
    public string $serverName = 'Swinx';

    public string $serverVersion = '0.1.0';

    public string $instructions = 'Swinx read-only staff assistant. Query allowlisted academic and finance metrics on behalf of the signed-in staff member, scoped to the campuses they are permitted to see.';

    /**
     * @var array<class-string>
     */
    protected array $tools = [
        PingTool::class,
    ];

    /**
     * @var array<class-string>
     */
    protected array $resources = [];

    /**
     * @var array<class-string>
     */
    protected array $prompts = [];
}
