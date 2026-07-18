<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

interface BillingAccountRollbackWriter
{
    /** Remove the empty Finance-owned account created with a fresh Student identity. */
    public function removeEmptyForStudent(int $studentId): void;
}
