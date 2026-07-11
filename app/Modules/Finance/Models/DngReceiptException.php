<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DngReceiptException extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $table = 'finance_dng_receipt_exceptions';

    protected $fillable = ['evidence_hash', 'exception_type', 'status', 'dng_payment_request_id', 'dng_webhook_event_id', 'payment_id', 'provider_payment_id', 'mismatch_reasons', 'affected_scope', 'raw_provider_evidence', 'resolved_at', 'resolved_by_user_id'];

    protected function casts(): array
    {
        return ['mismatch_reasons' => 'array', 'affected_scope' => 'array', 'raw_provider_evidence' => 'array', 'resolved_at' => 'datetime'];
    }

    public function dngPaymentRequest(): BelongsTo
    {
        return $this->belongsTo(DngPaymentRequest::class);
    }

    public function dngWebhookEvent(): BelongsTo
    {
        return $this->belongsTo(DngWebhookEvent::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
