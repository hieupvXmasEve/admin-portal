<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DngWebhookEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED_RETRYABLE = 'failed_retryable';

    public const STATUS_FAILED_TERMINAL = 'failed_terminal';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_MISMATCH = 'mismatch';

    public const ERROR_CATEGORY_CHECKSUM = 'checksum';

    public const ERROR_CATEGORY_NOT_FOUND = 'not_found';

    public const ERROR_CATEGORY_MALFORMED = 'malformed_payload';

    public const ERROR_CATEGORY_MISMATCH = 'mismatch';

    public const ERROR_CATEGORY_PROCESSING = 'processing';

    public const ERROR_CATEGORY_DUPLICATE = 'duplicate';

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
        'received_at',
        'attempt_count',
        'last_attempt_at',
        'next_retry_at',
        'error_category',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'payload' => 'array',
            'is_valid_checksum' => 'boolean',
            'processed_at' => 'datetime',
            'received_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
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

    public function markReceived(): void
    {
        $this->update([
            'processing_status' => self::STATUS_RECEIVED,
            'received_at' => $this->received_at ?? now(),
            'processed_at' => null,
            'error_message' => null,
            'error_category' => null,
        ]);
    }

    public function markProcessing(): void
    {
        $this->update([
            'processing_status' => self::STATUS_PROCESSING,
            'attempt_count' => $this->attempt_count + 1,
            'last_attempt_at' => now(),
            'next_retry_at' => null,
            'error_message' => null,
            'error_category' => null,
        ]);
    }

    public function markProcessed(): void
    {
        $this->update([
            'processing_status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
            'next_retry_at' => null,
            'error_message' => null,
            'error_category' => null,
        ]);
    }

    public function markFailedRetryable(string $errorMessage, string $errorCategory = self::ERROR_CATEGORY_PROCESSING, ?\DateTimeInterface $nextRetryAt = null): void
    {
        $this->update([
            'processing_status' => self::STATUS_FAILED_RETRYABLE,
            'processed_at' => null,
            'error_message' => $errorMessage,
            'error_category' => $errorCategory,
            'next_retry_at' => $nextRetryAt,
        ]);
    }

    public function markFailedTerminal(string $errorMessage, string $errorCategory): void
    {
        $this->update([
            'processing_status' => self::STATUS_FAILED_TERMINAL,
            'processed_at' => now(),
            'error_message' => $errorMessage,
            'error_category' => $errorCategory,
            'next_retry_at' => null,
        ]);
    }

    public function markMismatch(string $reason, string $errorCategory = self::ERROR_CATEGORY_MISMATCH): void
    {
        $this->update([
            'processing_status' => self::STATUS_MISMATCH,
            'processed_at' => now(),
            'error_message' => $reason,
            'error_category' => $errorCategory,
            'next_retry_at' => null,
        ]);
    }

    public function markSkipped(string $reason, string $errorCategory = self::ERROR_CATEGORY_DUPLICATE): void
    {
        $this->update([
            'processing_status' => self::STATUS_SKIPPED,
            'processed_at' => now(),
            'error_message' => $reason,
            'error_category' => $errorCategory,
            'next_retry_at' => null,
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
