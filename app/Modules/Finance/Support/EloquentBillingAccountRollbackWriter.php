<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Actions\RemoveEmptyBillingAccountAction;
use App\Shared\Contracts\Finance\BillingAccountRollbackWriter;

final class EloquentBillingAccountRollbackWriter implements BillingAccountRollbackWriter
{
    public function removeEmptyForStudent(int $studentId): void
    {
        RemoveEmptyBillingAccountAction::run(['student_id' => $studentId]);
    }
}
