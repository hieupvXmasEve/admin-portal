<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via Sanctum
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if the authenticated user is a User (admin/staff)
        if (!$request->user() instanceof \App\Models\User) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin account required.'
            ], 403);
        }

        // TODO: Add additional admin permission checks here
        // This could check for specific roles or permissions

        return $next($request);
    }
}
