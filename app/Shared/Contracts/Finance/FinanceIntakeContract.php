<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;

interface FinanceIntakeContract
{
    public function request(FinanceIntakeData $intake): FinanceIntakeResult;

    public function requestDebit(FinanceIntakeData $intake): FinanceIntakeResult;

    public function requestCredit(FinanceIntakeData $intake): FinanceIntakeResult;

    public function requestDiscount(FinanceIntakeData $intake): FinanceIntakeResult;
}
