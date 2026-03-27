<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Models;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DngPaymentRequest extends Model
{
    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_PUSHED_TO_DNG = 'pushed_to_dng';

    public const STATUS_QR_READY = 'qr_ready';

    public const STATUS_PAID_UNINVOICED = 'paid_uninvoiced';

    public const STATUS_PAID_INVOICED = 'paid_invoiced';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_FAILED = 'failed';

    /**
     * Allowed forward transitions. Key = current status, value = allowed next statuses.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PUSHED_TO_DNG, self::STATUS_FAILED],
        self::STATUS_PUSHED_TO_DNG => [self::STATUS_QR_READY, self::STATUS_PAID_UNINVOICED, self::STATUS_PAID_INVOICED, self::STATUS_FAILED],
        self::STATUS_QR_READY => [self::STATUS_PAID_UNINVOICED, self::STATUS_PAID_INVOICED, self::STATUS_FAILED],
        self::STATUS_PAID_UNINVOICED => [self::STATUS_PAID_INVOICED, self::STATUS_RECONCILED],
        self::STATUS_PAID_INVOICED => [self::STATUS_RECONCILED],
        self::STATUS_RECONCILED => [],
        self::STATUS_FAILED => [self::STATUS_PENDING],
    ];

    protected $fillable = [
        'student_id',
        'campus_code',
        'student_code',
        'fee_type',
        'item_id',
        'amount',
        'status',
        'dng_transaction_id',
        'dng_payment_id',
        'payment_id',
        'psp_code',
        'invoice_serial_number',
        'invoice_date',
        'paid_at',
        'push_payload',
        'push_response',
        'qr_payload',
        'last_callback_payload',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'invoice_date' => 'datetime',
            'paid_at' => 'datetime',
            'push_payload' => 'array',
            'push_response' => 'array',
            'qr_payload' => 'array',
            'last_callback_payload' => 'array',
        ];
    }

    // =====================
    // Relationships
    // =====================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(DngWebhookEvent::class);
    }

    // =====================
    // State Machine Guards
    // =====================

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];

        return in_array($newStatus, $allowed, true);
    }

    public function transitionTo(string $newStatus): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$this->status}' to '{$newStatus}'"
            );
        }

        $this->update(['status' => $newStatus]);
    }

    /**
     * Whether a Payment record has already been created for this request.
     */
    public function hasBridgedPayment(): bool
    {
        return $this->payment_id !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function callbackMismatchReasons(array $payload): array
    {
        $issues = [];

        $callbackAmount = (float) ($payload['Amount'] ?? 0);
        if (abs($callbackAmount - (float) $this->amount) > 0.01) {
            $issues[] = "Amount mismatch: local={$this->amount}, callback={$callbackAmount}";
        }

        $callbackStudentCode = (string) ($payload['StudentId'] ?? '');
        if ($callbackStudentCode !== '' && $callbackStudentCode !== $this->student_code) {
            $issues[] = "Student mismatch: local={$this->student_code}, callback={$callbackStudentCode}";
        }

        $callbackFeeType = (string) ($payload['FeeType'] ?? '');
        if ($callbackFeeType !== '' && $callbackFeeType !== $this->fee_type) {
            $issues[] = "Fee type mismatch: local={$this->fee_type}, callback={$callbackFeeType}";
        }

        return $issues;
    }

    // =====================
    // Scopes
    // =====================

    public function scopeStale($query, int $minutesThreshold = 120)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes($minutesThreshold));
    }

    public function scopeForCampus($query, string $campusCode)
    {
        return $query->where('campus_code', $campusCode);
    }

    public function scopeAwaitingInvoice($query)
    {
        return $query->where('status', self::STATUS_PAID_UNINVOICED);
    }
}
