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
        // 1. Skip for guests - let auth middleware handle them
        if (!auth()->check()) {
            return $next($request);
        }

        $campusId = session('current_campus_id');
        $isSelectCampusRoute = $request->routeIs([
            'select-campus.index',
            'select-campus.set-current',
            'select-campus.change'
        ]);

        // 2. If already has campus, and trying to go to selection page, go to dashboard
        if ($campusId && $isSelectCampusRoute && !$request->routeIs('select-campus.change')) {
            return redirect()->route('dashboard');
        }

        // 3. Whitelist routes that DON'T need campus selected
        if ($isSelectCampusRoute || $request->routeIs([
            'verification.notice',
            'verification.verify',
            'verification.send',
            'password.confirm',
            'logout',
        ])) {
            return $next($request);
        }

        // 4. If no campus selected, redirect to selection page
        if (!$campusId) {
            return redirect()->route('select-campus.index');
        }

        return $next($request);
    }
}
