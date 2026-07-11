<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Models;

use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DngPaymentRequestReservationTarget extends Model
{
    protected $fillable = [
        'dng_payment_request_id',
        'invoice_line_id',
        'finance_charge_installment_id',
        'captured_collectible',
        'target_identity',
    ];

    protected function casts(): array
    {
        return ['captured_collectible' => 'decimal:2'];
    }

    public function dngPaymentRequest(): BelongsTo
    {
        return $this->belongsTo(DngPaymentRequest::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    public function financeChargeInstallment(): BelongsTo
    {
        return $this->belongsTo(FinanceChargeInstallment::class);
    }
}
