<?php

declare(strict_types=1);

namespace App\Services\Admissions\Exceptions;

use App\Models\StudentApplication;
use RuntimeException;

/**
 * Raised when CRM ingestion tries to update an Application that is no longer
 * `pending`. Approval freezes the record (ADR-0003); the ingestion controller
 * maps this to a `409 Conflict` so the CRM knows the record must not change.
 */
class ApplicationFrozenException extends RuntimeException
{
    public function __construct(public readonly StudentApplication $application)
    {
        parent::__construct(
            "Application [{$application->crm_admission_id}] is frozen at approval (status: {$application->status})."
        );
    }
}
