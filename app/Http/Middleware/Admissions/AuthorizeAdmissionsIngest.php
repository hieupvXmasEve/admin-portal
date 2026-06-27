<?php

declare(strict_types=1);

namespace App\Http\Middleware\Admissions;

use App\Http\Responses\ApiResponse;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorises a call to the admissions ingestion group (ADR-0004).
 *
 * Runs after `auth:sanctum` (which yields a 401 for a missing/invalid token):
 *
 * 1. IP allowlist — when configured, the source IP must match the CRM's; an
 *    empty allowlist (local/dev/test) permits any IP.
 * 2. Token ability — the bearer token must carry `admissions:ingest`.
 *
 * Both failures return a 403 in the standard {@see ApiResponse} envelope.
 */
class AuthorizeAdmissionsIngest
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = (array) config('admissions.ingest.ip_allowlist', []);

        if ($allowlist !== [] && ! in_array($request->ip(), $allowlist, true)) {
            return ApiResponse::authorizationError('Source IP is not allowed for admissions ingestion.');
        }

        if (! $request->user()?->tokenCan(AdmissionsIngestion::ABILITY)) {
            return ApiResponse::authorizationError('This token lacks the admissions ingestion ability.');
        }

        return $next($request);
    }
}
