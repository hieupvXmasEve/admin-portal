<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ApiActorAuthorize
{
    private const ABILITY_MAP = [
        'student_or_parent' => 'api.actor.student_or_parent',
        'parent' => 'api.actor.parent',
        'lecturer' => 'api.actor.lecturer',
    ];

    public function handle(Request $request, Closure $next, string $actor): Response
    {
        $ability = self::ABILITY_MAP[$actor] ?? null;

        if (! $ability) {
            return ApiResponse::authorizationError("Unsupported API actor policy: {$actor}");
        }

        if (Gate::forUser($request->user())->denies($ability, $request)) {
            return ApiResponse::authorizationError('Actor is not authorized for this API surface');
        }

        return $next($request);
    }
}

