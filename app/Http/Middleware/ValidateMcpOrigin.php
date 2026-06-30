<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host/Origin allowlist guard for the public Controlled MCP endpoint (ADR-0011).
 *
 * `laravel/mcp`'s own README warns that `Mcp::web()` is exposed to DNS-rebinding: a
 * malicious page can point a victim-controlled DNS name at the server and drive the
 * browser to POST to it. Validating the `Host` header (the rebinding vector) and the
 * `Origin` header (cross-origin browser callers) against a tight allowlist closes
 * that gap. Non-browser agent clients (Claude, the MCP inspector) send no `Origin`,
 * so an absent `Origin` is allowed; a present one must match the allowlist.
 *
 * Attached at MCP route registration (routes/ai.php), never on the global stack, so
 * the rest of the application is untouched. Runs before the OAuth guard so a rebinding
 * probe is rejected without any authentication work.
 */
class ValidateMcpOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->hostAllowed($request) || ! $this->originAllowed($request)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32600,
                    'message' => 'Request rejected: the Host/Origin is not allowed for this MCP endpoint.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function hostAllowed(Request $request): bool
    {
        return in_array($this->normalizeHost($request->getHost()), $this->allowedHosts(), true);
    }

    private function originAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        // Non-browser MCP clients (Claude, the inspector) send no Origin header.
        if ($origin === null || $origin === '') {
            return true;
        }

        if (in_array($origin, config('mcp.allowed_origins', []), true)) {
            return true;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        return is_string($host) && in_array($this->normalizeHost($host), $this->allowedHosts(), true);
    }

    /**
     * @return list<string>
     */
    private function allowedHosts(): array
    {
        $configured = array_map(
            fn (string $host): string => $this->normalizeHost($host),
            config('mcp.allowed_hosts', [])
        );

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        // localhost/loopback always allowed: the MCP inspector and local development
        // connect from there, and they are not a public DNS-rebinding vector.
        $defaults = ['localhost', '127.0.0.1', '::1'];

        if (is_string($appHost) && $appHost !== '') {
            $defaults[] = $this->normalizeHost($appHost);
        }

        return array_values(array_unique([...$defaults, ...$configured]));
    }

    private function normalizeHost(string $host): string
    {
        // Drop any port and surrounding IPv6 brackets, lowercase for comparison.
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return trim($host, '[]');
    }
}
