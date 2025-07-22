<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLogging
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Log incoming request
        $this->logRequest($request);

        $response = $next($request);

        // Log response
        $this->logResponse($request, $response, $startTime);

        return $response;
    }

    /**
     * Log incoming API request
     */
    protected function logRequest(Request $request): void
    {
        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'user_type' => $request->user() ? get_class($request->user()) : null,
        ];

        // Add request data for non-GET requests (excluding sensitive data)
        if (!$request->isMethod('GET')) {
            $requestData = $request->all();
            
            // Remove sensitive fields
            $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key'];
            foreach ($sensitiveFields as $field) {
                unset($requestData[$field]);
            }
            
            $logData['request_data'] = $requestData;
        }

        Log::channel('api')->info('API Request', $logData);
    }

    /**
     * Log API response
     */
    protected function logResponse(Request $request, Response $response, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2); // in milliseconds

        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id' => $request->user()?->id,
        ];

        // Log response data for errors
        if ($response->getStatusCode() >= 400) {
            $content = $response->getContent();
            if ($content && $this->isJson($content)) {
                $logData['response_data'] = json_decode($content, true);
            }
        }

        $logLevel = $this->getLogLevel($response->getStatusCode());
        Log::channel('api')->{$logLevel}('API Response', $logData);

        // Log slow requests
        if ($duration > 1000) { // More than 1 second
            Log::channel('api')->warning('Slow API Request', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'duration_ms' => $duration,
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    /**
     * Get appropriate log level based on status code
     */
    protected function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };
    }

    /**
     * Check if content is valid JSON
     */
    protected function isJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
