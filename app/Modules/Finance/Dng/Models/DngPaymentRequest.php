<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Models;

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DngPaymentRequest extends Model
{
    // Status constants
    public const STATUS_PENDING = 'pending';

    public const STATUS_PUSHED_TO_DNG = 'pushed_to_dng';

    public const STATUS_PAID_UNINVOICED = 'paid_uninvoiced';

    public const STATUS_PAID_INVOICED = 'paid_invoiced';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Request was cancelled by calling DNG API with amount = -1.
     * Distinguishes from `cancelled` (closed locally without a provider-side
     * cancellation; the original request itself may already have reached DNG).
     */
    public const STATUS_CANCEL_PUSHED_TO_DNG = 'cancel_pushed_to_dng';

    public const STATUS_UNKNOWN_OUTCOME = 'unknown_outcome';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    /**
     * Allowed forward transitions. Key = current status, value = allowed next statuses.
     */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_PUSHED_TO_DNG, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_UNKNOWN_OUTCOME, self::STATUS_NEEDS_REVIEW],
        self::STATUS_PUSHED_TO_DNG => [self::STATUS_PAID_UNINVOICED, self::STATUS_PAID_INVOICED, self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_CANCEL_PUSHED_TO_DNG, self::STATUS_UNKNOWN_OUTCOME, self::STATUS_NEEDS_REVIEW],
        self::STATUS_PAID_UNINVOICED => [self::STATUS_PAID_INVOICED, self::STATUS_RECONCILED],
        self::STATUS_PAID_INVOICED => [self::STATUS_RECONCILED],
        self::STATUS_RECONCILED => [],
        self::STATUS_FAILED => [self::STATUS_PENDING],
        self::STATUS_UNKNOWN_OUTCOME => [self::STATUS_PAID_UNINVOICED, self::STATUS_PAID_INVOICED, self::STATUS_NEEDS_REVIEW, self::STATUS_CANCELLED],
        // Review blocks new collection mutations, but it must never discard a
        // later verified provider receipt for an already-pushed request.
        self::STATUS_NEEDS_REVIEW => [self::STATUS_PAID_UNINVOICED, self::STATUS_PAID_INVOICED, self::STATUS_CANCELLED],
        self::STATUS_CANCELLED => [],
        self::STATUS_CANCEL_PUSHED_TO_DNG => [],
    ];

    protected $fillable = [
        'student_id',
        'billing_account_id',
        'campus_code',
        'provider_rail',
        'student_code',
        'fee_type',
        'description',
        'semester_id',
        'due_date',
        'item_id',
        'active_slot_key',
        'amount',
        'captured_settlement_version',
        'target_fingerprint',
        'reserved_at',
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
        'last_reminder_at',
        'cancel_push_payload',
        'cancel_push_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'invoice_date' => 'datetime',
            'paid_at' => 'datetime',
            'reserved_at' => 'datetime',
            'push_payload' => 'array',
            'push_response' => 'array',
            'qr_payload' => 'array',
            'last_callback_payload' => 'array',
            'cancel_push_payload' => 'array',
            'cancel_push_response' => 'array',
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

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(DngWebhookEvent::class);
    }

    /**
     * Pivot links to all FinanceCharges covered by this DNG request.
     * Used for multi-charge (aggregate) DNG requests where one request covers
     * multiple retake-fee charges (one per unit for the same student).
     */
    public function chargeLinks(): HasMany
    {
        return $this->hasMany(DngPaymentRequestCharge::class);
    }

    public function reservationTargets(): HasMany
    {
        return $this->hasMany(DngPaymentRequestReservationTarget::class);
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
        return [
            ...$this->receiptAmountMismatchReasons($payload),
            ...$this->receiptCorrelationMismatchReasons($payload),
        ];
    }

    /** @param array<string, mixed> $payload @return array<int, string> */
    public function receiptAmountMismatchReasons(array $payload): array
    {
        $callbackAmount = (float) ($payload['Amount'] ?? 0);

        return abs($callbackAmount - (float) $this->amount) > 0.01
            ? ["Amount mismatch: local={$this->amount}, callback={$callbackAmount}"]
            : [];
    }

    /** @param array<string, mixed> $payload @return array<int, string> */
    public function receiptCorrelationMismatchReasons(array $payload): array
    {
        $issues = [];

        $callbackStudentCode = (string) ($payload['StudentId'] ?? '');
        if ($callbackStudentCode !== '' && $callbackStudentCode !== $this->student_code) {
            $issues[] = "Student mismatch: local={$this->student_code}, callback={$callbackStudentCode}";
        }

        // ItemId is the stable settle-once correlation key. When the callback carries
        // it, it must match — a callback that matched this request by PaymentId but
        // carries a different ItemId points at another debt and must be rejected.
        $callbackItemId = (string) ($payload['ItemId'] ?? '');
        if ($callbackItemId !== '' && $callbackItemId !== (string) $this->item_id) {
            $issues[] = "Item mismatch: local={$this->item_id}, callback={$callbackItemId}";
        }

        $callbackFeeType = (string) ($payload['FeeType'] ?? '');
        if ($callbackFeeType !== '' && $callbackFeeType !== $this->fee_type) {
            $issues[] = "Fee type mismatch: local={$this->fee_type}, callback={$callbackFeeType}";
        }

        // FIN-32: CampusCode is already inside the checksum string, so a valid
        // checksum guards it. This explicit business-field match is defense in
        // depth — it catches a payment routed to the wrong campus that happens to
        // share student + amount + fee type. Only enforced when the callback
        // actually carries a CampusCode.
        $callbackCampusCode = (string) ($payload['CampusCode'] ?? '');
        if ($callbackCampusCode !== '' && $callbackCampusCode !== (string) $this->campus_code) {
            $issues[] = "Campus mismatch: local={$this->campus_code}, callback={$callbackCampusCode}";
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

    public function scopeAwaitingPayment($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PUSHED_TO_DNG,
        ]);
    }

    /** @param Builder<self> $query */
    public function scopeHoldingCollection(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_PUSHED_TO_DNG,
            self::STATUS_UNKNOWN_OUTCOME,
            self::STATUS_NEEDS_REVIEW,
        ]);
    }
}
