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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Skip for guests - let auth middleware handle them
        if (! auth()->check()) {
            return $next($request);
        }

        // 1b. OAuth authorization endpoints render Passport's own consent screen for the
        // Controlled MCP server (ADR-0010) and are not part of the campus-scoped app shell.
        // They must never be bounced to campus selection, or the consent flow breaks.
        if ($request->is('oauth/*')) {
            return $next($request);
        }

        $campusId = session('current_campus_id');
        $isSelectCampusRoute = $request->routeIs([
            'select-campus.index',
            'select-campus.set-current',
        ]);

        // Only force the index page to dashboard. The set-current POST must stay
        // reachable so the sidebar switcher can change campus mid-session.
        if ($campusId && $request->routeIs('select-campus.index')) {
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
        if (! $campusId) {
            return redirect()->guest(route('select-campus.index'));
        }

        return $next($request);
    }
}
