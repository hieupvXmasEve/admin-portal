<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentMiddleware
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

        // Check if the authenticated user is a Student
        if (!$request->user() instanceof \App\Models\Student) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Student account required.'
            ], 403);
        }

        // Check if student account is active
        if ($request->user()->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Student account is not active'
            ], 403);
        }

        return $next($request);
    }
}
