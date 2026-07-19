<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\DngPaymentNotificationContext;

interface DngPaymentNotificationContextReader
{
    public function find(int $dngPaymentRequestId): ?DngPaymentNotificationContext;
}
