<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCampusSelected
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Nếu chưa login thì để middleware auth xử lý
        if (! auth()->check()) {
            return $next($request);
        }
        if (session()->has('current_campus_id') && $request->routeIs('select-campus.index')) {
            // go to dashboard
            return redirect()->route('dashboard');
        }
        // Skip kiểm tra cho các route select-campus, email verification, password confirmation, và logout
        if ($request->routeIs([
            'select-campus.index',
            'select-campus.set-current',
            'verification.notice',
            'verification.verify',
            'verification.send',
            'password.confirm',
            'logout',
        ])) {
            return $next($request);
        }

        // Redirect nếu chưa chọn campus
        if (! session()->has('current_campus_id')) {
            return redirect()->route('select-campus.index');
        }

        return $next($request);
    }
}
