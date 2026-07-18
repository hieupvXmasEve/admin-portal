<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\StudentLifecycleDeferData;

interface StudentLifecycleFinanceCommand
{
    public function applyDefer(StudentLifecycleDeferData $data): int;
}
