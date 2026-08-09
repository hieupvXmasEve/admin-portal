<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

/**
 * Whether a student has any finance charge at all (any type, any status).
 * Consumers outside Finance (e.g. StudentRegistry's revoke gate) go through
 * this contract instead of reaching into a Finance model or table directly.
 */
interface StudentFinanceChargeExistenceReader
{
    public function hasAnyChargeFor(int $studentId): bool;
}
