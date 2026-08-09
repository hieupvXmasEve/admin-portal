<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\Payment;
use App\Shared\Contracts\Finance\StudentPaymentExistenceReader;

/**
 * Implementation for App\Shared\Contracts\Finance\StudentPaymentExistenceReader.
 */
class StudentPaymentExistenceQuery implements StudentPaymentExistenceReader
{
    public function hasAnyPaymentFor(int $studentId): bool
    {
        return Payment::query()->forStudent($studentId)->exists();
    }
}
