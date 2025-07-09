<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        Log::info('Authenticate middleware: User not authenticated', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'is_api' => $request->expectsJson()
        ]);

        return $request->expectsJson() ? null : route('login');
    }

    /**
     * Handle an incoming request.
     */
    public function handle($request, \Closure $next, ...$guards)
    {
        Log::info('Authenticate middleware called', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'guards' => $guards,
            'user_authenticated' => Auth::check(),
            'user_id' => Auth::id()
        ]);

        return parent::handle($request, $next, ...$guards);
    }
}
