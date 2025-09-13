<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;

class EitherMiddleware
{
    protected Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Handle an incoming request.
     *
     * This middleware allows access if ANY of the specified middlewares pass.
     * The middlewares are tried in order, and if any one passes, access is granted.
     */
    public function handle(Request $request, Closure $next, string ...$middlewares): Response
    {
        if (empty($middlewares)) {
            return $next($request);
        }

        $lastResponse = null;

        foreach ($middlewares as $middleware) {
            try {
                // Flag to track if middleware passed
                $middlewarePassed = false;

                // Create a closure that sets flag when called (middleware passed)
                $mockNext = function () use (&$middlewarePassed) {
                    $middlewarePassed = true;
                    return response()->json(['middleware_test' => true]); // Dummy response
                };

                $response = $this->runMiddleware($request, $middleware, $mockNext);

                // If middleware passed (called next), grant access
                if ($middlewarePassed) {
                    return $next($request);
                }

                // Store the last error response
                if ($response instanceof Response) {
                    $lastResponse = $response;
                }
            } catch (\Exception $e) {
                // Continue to next middleware if current one fails
                continue;
            }
        }

        // If all middlewares failed, return the last error response
        return $lastResponse ?? response()->json([
            'message' => 'Access denied. None of the required middlewares passed.',
            'success' => false
        ], 403);
    }

    /**
     * Run a specific middleware and return its response
     */
    protected function runMiddleware(Request $request, string $middleware, Closure $mockNext): ?Response
    {
        // Resolve middleware instance from alias or class name
        $middlewareInstance = $this->resolveMiddleware($middleware);

        if (!$middlewareInstance) {
            return response()->json([
                'message' => "Middleware '{$middleware}' not found",
                'success' => false
            ], 500);
        }

        // Run the middleware
        return $middlewareInstance->handle($request, $mockNext);
    }

    /**
     * Resolve middleware from alias or class name
     */
    protected function resolveMiddleware(string $middleware): ?object
    {
        // Get all registered middleware aliases
        $middlewareAliases = $this->router->getMiddleware();

        // If it's an alias, resolve to class
        if (isset($middlewareAliases[$middleware])) {
            $middlewareClass = $middlewareAliases[$middleware];
            return app($middlewareClass);
        }

        // If it's already a class name, resolve directly
        if (class_exists($middleware)) {
            return app($middleware);
        }

        return null;
    }
}
