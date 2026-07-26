<?php

declare(strict_types=1);

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Shared\Contracts\StudentRegistry\StudentRegistered;

final class ProvisionBillingAccountForRegisteredStudent
{
    public function __construct(private readonly BillingAccountProvisioner $billingAccounts) {}

    public function handle(StudentRegistered $event): void
    {
        $this->billingAccounts->forStudent($event->studentId);
    }
}
