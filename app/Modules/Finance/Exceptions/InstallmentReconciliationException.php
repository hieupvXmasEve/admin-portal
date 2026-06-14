<?php

declare(strict_types=1);

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

/**
 * Thrown when a charge's installment plan can no longer be reconciled to its net
 * due after a discount change (FIN-09).
 *
 * The safe, automatic path recomputes the *pending* installments to absorb the
 * delta. This exception is raised only when the delta cannot be absorbed by
 * pending rows — i.e. already committed installments (awaiting_payment / paid,
 * possibly already pushed to DNG) would themselves have to change. That is a
 * human-review situation, not something to silently rewrite.
 */
class InstallmentReconciliationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct("[{$errorCode}] {$message}");
    }
}
