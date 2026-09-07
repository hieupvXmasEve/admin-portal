<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSurplusDisposition extends Model
{
    public const TYPE_REALLOCATE = 'reallocate';

    /** Historical enum value. New writes are rejected by policy. */
    public const TYPE_REFUND = 'refund';

    public const TYPE_RETAIN_FORFEIT = 'retain_forfeit';

    public const REFUND_BLOCKED_MESSAGE = 'chính sách hiện tại không hoàn tiền';

    protected $fillable = [
        'payment_id', 'idempotency_key', 'type', 'amount', 'payment_application_id',
        'external_reference', 'policy_code', 'reason', 'evidence', 'audit_signature',
        'approved_by', 'disposed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'evidence' => 'array',
        'disposed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function paymentApplication(): BelongsTo
    {
        return $this->belongsTo(PaymentApplication::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
