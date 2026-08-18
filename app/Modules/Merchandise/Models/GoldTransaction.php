<?php

namespace App\Modules\Merchandise\Models;

use App\Models\Student;
use App\Models\StudentWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldTransaction extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'gold_transactions';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'student_id',
        'amount',
        'balance_before',
        'balance_after',
        'type',
        'source_type',
        'source_id',
        'performed_by',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     * Only tracks created_at, not updated_at.
     */
    public $timestamps = false;

    /**
     * Transaction types (backend allow-list — no DB enum).
     */
    public const TYPE_EARN = 'earn';

    public const TYPE_SPEND = 'spend';

    public const TYPE_ADJUST = 'adjust';

    public const TYPE_REWARD = 'reward';

    public const TYPE_REDEMPTION = 'redemption';

    public const TYPE_REDEMPTION_REFUND = 'redemption_refund';

    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    // Gold that could not be reclaimed (student had already spent it). Audit
    // only — a write_off never moves balance, so its amount is always 0.
    public const TYPE_WRITE_OFF = 'write_off';

    /**
     * Allowed transaction types.
     *
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_EARN,
        self::TYPE_SPEND,
        self::TYPE_ADJUST,
        self::TYPE_REWARD,
        self::TYPE_REDEMPTION,
        self::TYPE_REDEMPTION_REFUND,
        self::TYPE_MANUAL_ADJUSTMENT,
        self::TYPE_WRITE_OFF,
    ];

    /**
     * Source types (backend allow-list — no DB enum).
     */
    public const SOURCE_EVENT = 'event';

    public const SOURCE_REWARD = 'reward';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_REDEMPTION_ORDER = 'redemption_order';

    /**
     * Allowed source types.
     *
     * @var list<string>
     */
    public const SOURCE_TYPES = [
        self::SOURCE_EVENT,
        self::SOURCE_REWARD,
        self::SOURCE_MANUAL,
        self::SOURCE_REDEMPTION_ORDER,
    ];

    /**
     * Get the student that owns this transaction.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * Get the wallet for this transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(StudentWallet::class, 'student_id', 'student_id');
    }

    /**
     * Scope a query to only include transactions of a given type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include transactions from a given source type.
     */
    public function scopeFromSource(Builder $query, string $sourceType): Builder
    {
        return $query->where('source_type', $sourceType);
    }

    /**
     * Scope a query to only include earning transactions.
     */
    public function scopeEarnings(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_EARN);
    }

    /**
     * Scope a query to only include spending transactions.
     */
    public function scopeSpending(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_SPEND);
    }

    /**
     * Scope a query to only include adjustment transactions.
     */
    public function scopeAdjustments(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ADJUST);
    }

    /**
     * Check if this transaction is positive (increases balance).
     */
    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if this transaction is negative (decreases balance).
     */
    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Get the performing staff user (null for legacy / system entries).
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get the absolute amount of the transaction (integer Gold).
     */
    public function getAbsoluteAmountAttribute(): int
    {
        return abs((int) $this->amount);
    }
}
