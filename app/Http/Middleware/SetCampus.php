<?php

namespace App\Http\Middleware;

use App\Models\Campus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCampus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('current_campus_id')) {
            $campusId = session('current_campus_id');
            $campus = Campus::find($campusId);

            if ($campus) {
                app()->singleton('campus', fn () => $campus);
            }
        }

        return $next($request);
    }
}
