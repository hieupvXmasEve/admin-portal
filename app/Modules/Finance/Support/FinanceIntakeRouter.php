<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Actions\RequestFinanceCreditAction;
use App\Modules\Finance\Actions\RequestFinanceDebitAction;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\Exceptions\UnsupportedFinancialEffectYet;
use App\Shared\Contracts\Finance\FinanceIntakeContract;

class FinanceIntakeRouter implements FinanceIntakeContract
{
    public function __construct(
        private readonly RequestFinanceDebitAction $requestDebitAction,
        private readonly RequestFinanceCreditAction $requestCreditAction,
    ) {}

    public function request(FinanceIntakeData $intake): FinanceIntakeResult
    {
        return match ($intake->financial_effect) {
            FinancialEffect::Debit => $this->requestDebit($intake),
            FinancialEffect::Credit => $this->requestCredit($intake),
            FinancialEffect::Discount => throw UnsupportedFinancialEffectYet::forEffect($intake->financial_effect),
        };
    }

    public function requestDebit(FinanceIntakeData $intake): FinanceIntakeResult
    {
        if ($intake->financial_effect !== FinancialEffect::Debit) {
            throw UnsupportedFinancialEffectYet::forEffect($intake->financial_effect);
        }

        return $this->requestDebitAction->handle($intake);
    }

    public function requestCredit(FinanceIntakeData $intake): FinanceIntakeResult
    {
        if ($intake->financial_effect !== FinancialEffect::Credit) {
            throw UnsupportedFinancialEffectYet::forEffect($intake->financial_effect);
        }

        return $this->requestCreditAction->handle($intake);
    }
}
