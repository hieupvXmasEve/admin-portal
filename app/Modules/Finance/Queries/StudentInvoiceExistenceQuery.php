<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\StudentInvoiceExistenceReader;

/**
 * Implementation for App\Shared\Contracts\Finance\StudentInvoiceExistenceReader.
 */
class StudentInvoiceExistenceQuery implements StudentInvoiceExistenceReader
{
    public function hasAnyInvoiceFor(int $studentId): bool
    {
        return StudentInvoice::query()->where('student_id', $studentId)->exists();
    }
}
