<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admissions;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admissions-CRM ingestion endpoints (server-to-server, ADR-0004).
 *
 * This slice (07) ships only the health/ping endpoint that proves the
 * authenticated, hardened path end-to-end; the create/update payload handlers
 * land in slice 08.
 */
class IngestionController extends Controller
{
    /**
     * Health check for the ingestion path. Reaching this means the caller
     * presented a valid token carrying the `admissions:ingest` ability and
     * passed the IP allowlist and rate limiter.
     */
    public function ping(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'service' => 'admissions-ingestion',
                'ability' => AdmissionsIngestion::ABILITY,
            ],
            message: 'pong',
        );
    }
}
