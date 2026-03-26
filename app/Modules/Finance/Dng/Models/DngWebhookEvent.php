<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DngWebhookEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_MISMATCH = 'mismatch';

    public const EVENT_PAYMENT_WITHOUT_INVOICE = 'payment_succeeded_without_invoice';

    public const EVENT_PAYMENT_INVOICED = 'payment_invoiced';

    protected $fillable = [
        'dng_payment_id',
        'event_type',
        'payload_hash',
        'headers',
        'payload',
        'is_valid_checksum',
        'processed_at',
        'processing_status',
        'dng_payment_request_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'is_valid_checksum' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    // =====================
    // Relationships
    // =====================

    public function dngPaymentRequest(): BelongsTo
    {
        return $this->belongsTo(DngPaymentRequest::class);
    }

    // =====================
    // Helpers
    // =====================

    public function markProcessed(): void
    {
        $this->update([
            'processing_status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'processing_status' => self::STATUS_FAILED,
            'processed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    public function markMismatch(string $reason): void
    {
        $this->update([
            'processing_status' => self::STATUS_MISMATCH,
            'processed_at' => now(),
            'error_message' => $reason,
        ]);
    }

    /**
     * Generate a deterministic hash for dedup.
     */
    public static function computePayloadHash(array $payload): string
    {
        // Sort keys for consistency, then SHA-256
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Determine event type from payload fields.
     */
    public static function resolveEventType(array $payload): string
    {
        $hasInvoice = filled($payload['InvoiceSerialNumber'] ?? null)
            && filled($payload['InvoiceDate'] ?? null);

        return $hasInvoice
            ? self::EVENT_PAYMENT_INVOICED
            : self::EVENT_PAYMENT_WITHOUT_INVOICE;
    }
}
