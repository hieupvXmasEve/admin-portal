<?php

declare(strict_types=1);

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

/**
 * Thrown when an installment plan submitted to SplitChargeIntoInstallmentsAction
 * fails domain invariants (sum mismatch, non-sequential numbers, due_date order).
 * FormRequest catches most cases upstream; this exception covers cases that
 * require fetching the charge (e.g. sum vs net target comparison).
 */
class InvalidInstallmentPlanException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct("[{$errorCode}] {$message}");
    }
}
