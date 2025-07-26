<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class LecturerApiRateLimiter
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limiter = 'lecturer-api'): Response
    {
        $key = $this->resolveRequestSignature($request, $limiter);
        
        // Define rate limits based on endpoint type
        $limits = $this->getRateLimits($limiter);
        
        if (RateLimiter::tooManyAttempts($key, $limits['maxAttempts'])) {
            $retryAfter = RateLimiter::availableIn($key);
            
            return ApiResponse::rateLimitError(
                "Too many requests. Try again in {$retryAfter} seconds."
            )->header('Retry-After', $retryAfter)
             ->header('X-RateLimit-Limit', $limits['maxAttempts'])
             ->header('X-RateLimit-Remaining', 0);
        }

        RateLimiter::hit($key, $limits['decayMinutes'] * 60);

        $response = $next($request);

        // Add rate limit headers to successful responses
        $remaining = RateLimiter::remaining($key, $limits['maxAttempts']);
        
        return $response->header('X-RateLimit-Limit', $limits['maxAttempts'])
                       ->header('X-RateLimit-Remaining', max(0, $remaining));
    }

    /**
     * Resolve the request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request, string $limiter): string
    {
        $user = $request->user();
        $userId = $user ? $user->id : $request->ip();
        
        return "lecturer-api:{$limiter}:{$userId}";
    }

    /**
     * Get rate limits based on limiter type
     */
    protected function getRateLimits(string $limiter): array
    {
        return match ($limiter) {
            'lecturer-auth' => [
                'maxAttempts' => 5,
                'decayMinutes' => 15,
            ],
            'lecturer-dashboard' => [
                'maxAttempts' => 30,
                'decayMinutes' => 1,
            ],
            'lecturer-attendance' => [
                'maxAttempts' => 100, // Higher limit for attendance marking
                'decayMinutes' => 1,
            ],
            'lecturer-course-management' => [
                'maxAttempts' => 50,
                'decayMinutes' => 1,
            ],
            'lecturer-timetable' => [
                'maxAttempts' => 40,
                'decayMinutes' => 1,
            ],
            'lecturer-student-management' => [
                'maxAttempts' => 60,
                'decayMinutes' => 1,
            ],
            'lecturer-reports' => [
                'maxAttempts' => 10, // Lower limit for report generation
                'decayMinutes' => 5,
            ],
            'lecturer-profile' => [
                'maxAttempts' => 20,
                'decayMinutes' => 1,
            ],
            'lecturer-notifications' => [
                'maxAttempts' => 30,
                'decayMinutes' => 1,
            ],
            'lecturer-api' => [
                'maxAttempts' => 120, // Higher default limit for lecturers
                'decayMinutes' => 1,
            ],
            default => [
                'maxAttempts' => 60,
                'decayMinutes' => 1,
            ],
        };
    }
}
