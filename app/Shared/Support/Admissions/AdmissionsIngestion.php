<?php

declare(strict_types=1);

namespace App\Shared\Support\Admissions;

/**
 * Shared constants for the admissions-CRM server-to-server ingestion path
 * (ADR-0004). Centralised so the token ability, limiter name, and audit log
 * channel never drift between the minting command, the route middleware, and
 * the tests.
 */
final class AdmissionsIngestion
{
    /**
     * Sanctum token ability that scopes a service token to admissions ingestion
     * and nothing else.
     */
    public const ABILITY = 'admissions:ingest';

    /**
     * Name attached to minted personal access tokens, for display/revocation.
     */
    public const TOKEN_NAME = 'admissions-crm-ingest';

    /**
     * Named rate limiter applied to the ingestion route group.
     */
    public const RATE_LIMITER = 'admissions-ingest';

    /**
     * Activity-log channel for the per-call ingestion audit trail.
     */
    public const AUDIT_LOG = 'admissions-ingestion';
}
