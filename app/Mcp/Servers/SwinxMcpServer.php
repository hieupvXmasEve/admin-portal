<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetEntityProfileMcpTool;
use App\Mcp\Tools\QueryMetricsMcpTool;
use App\Mcp\Tools\SearchEntitiesMcpTool;
use Laravel\Mcp\Server;

/**
 * Controlled MCP server (ADR-0009) exposing Swinx's read-only AI tools to external
 * agent clients under per-user OAuth impersonation.
 *
 * All three canonical Query-only AI capability tools are now registered:
 * `query_metrics`, `search_entities`, and `get_entity_profile`. The `ping` spike
 * tracer has been removed now that real tools exercise the transport. The server is
 * reached at `POST /mcp/swinx` behind the `api` (Passport) guard — see routes/ai.php.
 */
class SwinxMcpServer extends Server
{
    public string $serverName = 'Swinx';

    public string $serverVersion = '0.1.0';

    public string $instructions = 'Swinx read-only staff assistant. Query allowlisted academic and finance metrics, search academic entities, and pull allowlisted student profile sections on behalf of the signed-in staff member, scoped to the campuses they are permitted to see.';

    /**
     * @var array<class-string>
     */
    protected array $tools = [
        QueryMetricsMcpTool::class,
        SearchEntitiesMcpTool::class,
        GetEntityProfileMcpTool::class,
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
