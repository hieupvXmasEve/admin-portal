<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckCampusSelected
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Nếu chưa login thì để middleware auth xử lý
        if (!auth()->check()) {
            return $next($request);
        }

        // Skip kiểm tra cho các route select-campus
        if ($request->routeIs(['select-campus.index', 'select-campus.set-current'])) {
            return $next($request);
        }

        // Redirect nếu chưa chọn campus
        if (!session()->has('current_campus_id')) {
            return redirect()->route('select-campus.index');
        }

        return $next($request);
    }
}
