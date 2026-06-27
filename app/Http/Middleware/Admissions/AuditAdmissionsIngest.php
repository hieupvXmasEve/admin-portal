<?php

declare(strict_types=1);

namespace App\Http\Middleware\Admissions;

use App\Shared\Support\Admissions\AdmissionsIngestion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records an audit-log entry for every authenticated admissions ingestion call
 * (ADR-0004): who (the service account + token id), when, which route/IP, and
 * the resulting status.
 *
 * Mounted after `auth:sanctum` but outside the authorization check, so both
 * successful calls and authenticated-but-denied attempts (bad IP / missing
 * ability, which return a 403) are traced with their causer. Calls rejected
 * before authentication (401) or by the throttle (429) unwind as exceptions
 * handled globally and are out of this middleware's scope.
 */
class AuditAdmissionsIngest
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $token = $user?->currentAccessToken();

        activity(AdmissionsIngestion::AUDIT_LOG)
            ->causedBy($user)
            ->withProperties([
                'token_id' => $token?->getKey(),
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
            ])
            ->event('ingestion.call')
            ->log('Admissions ingestion call');

        return $response;
    }
}
