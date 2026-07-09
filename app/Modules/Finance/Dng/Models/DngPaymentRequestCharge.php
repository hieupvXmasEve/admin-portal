<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Models;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model: an allocation line of ONE DNG payment request.
 *
 * `amount` is how much of THIS request is attributed to the charge — not the
 * charge's remaining balance. When the request is built from an installment plan
 * the row also carries `finance_charge_installment_id`, so the pivot amount
 * equals the installment being collected (allocation truth == installment truth,
 * FIN-10b). Legacy / ad-hoc (override) rows leave the installment link null.
 */
class DngPaymentRequestCharge extends Model
{
    protected $fillable = [
        'dng_payment_request_id',
        'finance_charge_id',
        'finance_charge_installment_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function dngPaymentRequest(): BelongsTo
    {
        return $this->belongsTo(DngPaymentRequest::class);
    }

    public function financeCharge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class);
    }

    public function financeChargeInstallment(): BelongsTo
    {
        return $this->belongsTo(FinanceChargeInstallment::class);
    }
}
