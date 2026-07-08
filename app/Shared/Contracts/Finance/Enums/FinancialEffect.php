<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance\Enums;

enum FinancialEffect: string
{
    case Debit = 'debit';
    case Credit = 'credit';
    case Discount = 'discount';
}
