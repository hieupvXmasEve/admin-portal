<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Models;

use App\Models\FinanceCharge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model linking a DNG payment request to one or more finance charges.
 *
 * Used when a single DNG request covers multiple retake-fee charges (one per unit).
 * The amount field records how much of the DNG total is attributed to each charge,
 * enabling precise per-charge payment allocation on webhook confirmation.
 */
class DngPaymentRequestCharge extends Model
{
    protected $fillable = [
        'dng_payment_request_id',
        'finance_charge_id',
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
}
