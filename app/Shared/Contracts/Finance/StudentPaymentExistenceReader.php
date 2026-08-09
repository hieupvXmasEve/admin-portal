<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

/**
 * Whether a student has any payment at all (any status).
 * Consumers outside Finance (e.g. StudentRegistry's revoke gate) go through
 * this contract instead of reaching into a Finance model or table directly.
 */
interface StudentPaymentExistenceReader
{
    public function hasAnyPaymentFor(int $studentId): bool;
}
