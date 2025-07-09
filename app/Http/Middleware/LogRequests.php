<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('=== INCOMING REQUEST ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
            'csrf_token' => $request->header('X-CSRF-TOKEN'),
            'content_type' => $request->header('Content-Type'),
            'accept' => $request->header('Accept'),
            'is_authenticated' => Auth::check(),
            'user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
            'timestamp' => now()->toISOString()
        ]);

        $response = $next($request);

        Log::info('=== RESPONSE SENT ===', [
            'status_code' => $response->getStatusCode(),
            'redirect_to' => $response instanceof \Illuminate\Http\RedirectResponse ? $response->getTargetUrl() : null,
            'timestamp' => now()->toISOString()
        ]);

        return $response;
    }
}
