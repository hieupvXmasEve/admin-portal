<?php

declare(strict_types=1);

namespace App\Modules\Finance\Exceptions;

use RuntimeException;

/**
 * Thrown when the charge itself is not eligible for installment split:
 * - Credit charges (amount <= 0): credits are settled via invoice allocation, not student payment.
 * - Fully credited charges (net target <= 0): nothing left to collect.
 * - Voided charges.
 */
class InstallmentSplitNotAllowedException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct("[{$errorCode}] {$message}");
    }
}
