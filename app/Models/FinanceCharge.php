<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinanceCharge extends Model
{
    protected $fillable = [
        'finance_obligation_id',
        'student_id',
        'semester_id',
        'billing_cycle_id',
        'charge_type',
        'amount',
        'description',
        'effective_at',
        'status',
        'source_type',
        'source_id',
        'active_source_key',
        'created_by_user_id',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected $casts = [
        // FIN-08: align with the DB column decimal(15,2). decimal:0 truncated the
        // fractional part on read, which systematically skewed percentage-based
        // scholarship math (amount * pct / 100) by up to a unit.
        'amount' => 'decimal:2',
        'effective_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    // =====================
    // Constants
    // =====================

    public const TYPE_TUITION_TERM = 'tuition_term';

    public const TYPE_EGC_LEVEL_FEE = 'egc_level_fee';

    public const TYPE_RETAKE_FEE = 'retake_fee';

    public const TYPE_EXAM_RESIT_FEE = 'exam_resit_fee';

    public const TYPE_COURSE_FEE = 'course_fee';

    public const TYPE_MANUAL_FEE = 'manual_fee';

    public const TYPE_ADMISSION_FEE = 'admission_fee';

    public const TYPE_DEFER_CREDIT = 'defer_credit';

    public const TYPE_EGC_EXEMPT_CREDIT = 'egc_exempt_credit';

    public const TYPE_SCHOLARSHIP_CREDIT = 'scholarship_credit';

    public const TYPE_VOUCHER_CREDIT = 'voucher_credit';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_BHYT = 'bhyt';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_VOID = 'void';

    // FIN-25: keep this list in lockstep with the finance_charges.charge_type DB
    // enum. course_fee and bhyt are valid DB values and were missing here.
    public const CHARGE_TYPES = [
        self::TYPE_TUITION_TERM,
        self::TYPE_EGC_LEVEL_FEE,
        self::TYPE_RETAKE_FEE,
        self::TYPE_EXAM_RESIT_FEE,
        self::TYPE_COURSE_FEE,
        self::TYPE_MANUAL_FEE,
        self::TYPE_ADMISSION_FEE,
        self::TYPE_DEFER_CREDIT,
        self::TYPE_EGC_EXEMPT_CREDIT,
        self::TYPE_SCHOLARSHIP_CREDIT,
        self::TYPE_VOUCHER_CREDIT,
        self::TYPE_ADJUSTMENT,
        self::TYPE_BHYT,
    ];

    /**
     * Charge types that represent a credit (negative ledger entry). Used to keep
     * model logic and the finance_charges money-sign DB CHECK aligned (DB-06).
     *
     * @var array<int, string>
     */
    public const CREDIT_CHARGE_TYPES = [
        self::TYPE_DEFER_CREDIT,
        self::TYPE_EGC_EXEMPT_CREDIT,
        self::TYPE_SCHOLARSHIP_CREDIT,
        self::TYPE_VOUCHER_CREDIT,
    ];

    // =====================
    // Relationships
    // =====================

    protected static function booted(): void
    {
        static::saving(function (FinanceCharge $charge): void {
            $charge->active_source_key = $charge->buildActiveSourceKey();
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function financeObligation(): BelongsTo
    {
        return $this->belongsTo(FinanceObligation::class, 'finance_obligation_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function billingCycle(): BelongsTo
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'charge_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FinanceChargeInstallment::class)
            ->orderBy('installment_no');
    }

    public function hasPaidInstallment(): bool
    {
        return $this->installments()
            ->where('status', FinanceChargeInstallment::STATUS_PAID)
            ->exists();
    }

    // =====================
    // Scopes
    // =====================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeVoid($query)
    {
        return $query->where('status', self::STATUS_VOID);
    }

    public function scopeCharges($query)
    {
        return $query->where('amount', '>', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeForSemester($query, int $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    // =====================
    // Accessors
    // =====================

    public function getIsChargeAttribute(): bool
    {
        return $this->amount > 0;
    }

    public function getIsCreditAttribute(): bool
    {
        return $this->amount < 0;
    }

    public function getPaidAmountAttribute(): float
    {
        return app(SettlementService::class)->getChargePaidAmount($this->id);
    }

    /**
     * Total discount (scholarship/voucher) applied to this charge's invoice lines.
     */
    public function getDiscountAmountAttribute(): float
    {
        return app(SettlementService::class)->getChargeDiscountAmount($this->id);
    }

    public function getBalanceAttribute(): float
    {
        if ($this->amount <= 0) {
            return 0; // Credits don't have balance
        }

        // Subtract both cash payments and discount allocations (scholarship/voucher)
        return max(0, (float) $this->amount - $this->paid_amount - $this->discount_amount);
    }

    public function getIsFullyPaidAttribute(): bool
    {
        return $this->balance <= 0;
    }

    // =====================
    // Methods
    // =====================

    public function void(int $userId, string $reason): self
    {
        $this->update([
            'status' => self::STATUS_VOID,
            'voided_at' => now(),
            'voided_by_user_id' => $userId,
            'void_reason' => $reason,
        ]);

        return $this;
    }

    public function buildActiveSourceKey(): ?string
    {
        if (
            $this->status !== self::STATUS_ACTIVE
            || blank($this->source_type)
            || blank($this->source_id)
            || blank($this->charge_type)
        ) {
            return null;
        }

        return implode('|', [
            $this->source_type,
            (string) $this->source_id,
            $this->charge_type,
        ]);
    }
}
