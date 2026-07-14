<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Entitlement;

/**
 * Entitlement vocabulary is deliberately separate from FinanceCharge's active
 * debit registry. These identifiers remain readable on historical void charge
 * rows and are the values persisted by credit and discount entitlements.
 */
final class FinanceEntitlementType
{
    public const DeferCredit = 'defer_credit';

    public const EgcExemptCredit = 'egc_exempt_credit';

    public const ScholarshipCredit = 'scholarship_credit';

    public const VoucherCredit = 'voucher_credit';

    /** @var list<string> */
    public const CREDIT_TYPES = [
        self::DeferCredit,
        self::EgcExemptCredit,
        self::ScholarshipCredit,
    ];

    /** @var list<string> */
    public const DISCOUNT_TYPES = [
        self::ScholarshipCredit,
        self::VoucherCredit,
    ];

    /** @var list<string> */
    public const HISTORICAL_CHARGE_TYPES = [
        self::DeferCredit,
        self::EgcExemptCredit,
        self::ScholarshipCredit,
        self::VoucherCredit,
    ];
}
