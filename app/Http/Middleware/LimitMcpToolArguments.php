<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tool-argument length ceiling for the public Controlled MCP endpoint (ADR-0011).
 *
 * The three tools accept caller-supplied `arguments`; an oversized payload is a
 * memory-pressure and prompt-injection vector. This caps the JSON-encoded size of a
 * single tool call's `arguments` object before it reaches the transport/tool layer.
 *
 * Attached at MCP route registration (routes/ai.php) ahead of the OAuth guard so a
 * hostile body is rejected cheaply, without authentication work. Only `tools/call`
 * carries arguments; every other JSON-RPC method passes straight through.
 */
class LimitMcpToolArguments
{
    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->json()->all();

        $arguments = is_array($payload) ? ($payload['params']['arguments'] ?? null) : null;

        if ($arguments !== null) {
            $encoded = json_encode($arguments);
            $max = (int) config('mcp.max_argument_bytes', 8192);

            if ($encoded === false || strlen($encoded) > $max) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'id' => is_array($payload) ? ($payload['id'] ?? null) : null,
                    'error' => [
                        'code' => -32600,
                        'message' => 'Tool arguments exceed the maximum permitted size.',
                    ],
                ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
            }
        }

        return $next($request);
    }
}
