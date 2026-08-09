<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Finance\StudentFinanceChargeExistenceReader;

/**
 * Implementation for App\Shared\Contracts\Finance\StudentFinanceChargeExistenceReader.
 */
class StudentFinanceChargeExistenceQuery implements StudentFinanceChargeExistenceReader
{
    public function hasAnyChargeFor(int $studentId): bool
    {
        return FinanceCharge::query()->where('student_id', $studentId)->exists();
    }
}
