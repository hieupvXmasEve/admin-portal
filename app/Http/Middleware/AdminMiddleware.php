<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('Admin middleware called', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'bearerToken' => $request->bearerToken(),
        ]);
        // Check if user is authenticated via Sanctum
        if (! $request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Check if the authenticated user is a User (admin/staff)
        if (! $request->user() instanceof \App\Models\User) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin account required.',
            ], 403);
        }

        // TODO: Add additional admin permission checks here
        // This could check for specific roles or permissions

        return $next($request);
    }
}
