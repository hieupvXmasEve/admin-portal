<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RedemptionOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RedemptionOrder extends AuditableModel
{
    /** @use HasFactory<RedemptionOrderFactory> */
    use HasFactory;

    protected $table = 'redemption_orders';

    protected $fillable = [
        'student_id',
        'campus_id',
        'code',
        'status',
        'previous_status',
        'method',
        'total_gold',
        'idempotency_key',
        'collection_location',
        'ready_at',
        'collection_deadline',
        'collected_at',
        'collected_confirmed_by',
        'collection_note',
        'shipping_address',
        'shipped_at',
        'shipped_by',
        'shipping_note',
        'reject_reason',
        'cancellation_requested_by',
        'cancellation_requested_at',
        'cancellation_reason',
        'cancellation_handled_by',
        'cancellation_handled_at',
        'cancellation_result',
        'cancellation_note',
        'cancelled_at',
    ];

    protected $casts = [
        'total_gold' => 'integer',
        'ready_at' => 'datetime',
        'collection_deadline' => 'datetime',
        'collected_at' => 'datetime',
        'shipped_at' => 'datetime',
        'cancellation_requested_at' => 'datetime',
        'cancellation_handled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Statuses (backend allow-list — no DB enum).
     */
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_READY_FOR_COLLECTION = 'ready_for_collection';

    public const STATUS_PICKUP_OVERDUE = 'pickup_overdue';

    public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_PENDING_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_READY_FOR_COLLECTION,
        self::STATUS_PICKUP_OVERDUE,
        self::STATUS_CANCELLATION_REQUESTED,
        self::STATUS_COLLECTED,
        self::STATUS_SHIPPED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Statuses that never refunded/refunded Gold and stock back — i.e. the
     * order actually delivered or is still in flight for gold-spend purposes.
     * Used for the "Total Redeemed" dashboard metric (D8 default).
     *
     * @var list<string>
     */
    public const NON_REVERSED_STATUSES = [
        self::STATUS_PENDING_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_READY_FOR_COLLECTION,
        self::STATUS_PICKUP_OVERDUE,
        self::STATUS_CANCELLATION_REQUESTED,
        self::STATUS_COLLECTED,
        self::STATUS_SHIPPED,
    ];

    public const METHOD_PICKUP = 'pickup';

    public const METHOD_SHIPPING = 'shipping';

    /**
     * @var list<string>
     */
    public const METHODS = [
        self::METHOD_PICKUP,
        self::METHOD_SHIPPING,
    ];

    public const CANCELLATION_RESULT_ACCEPTED = 'accepted';

    public const CANCELLATION_RESULT_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    public const CANCELLATION_RESULTS = [
        self::CANCELLATION_RESULT_ACCEPTED,
        self::CANCELLATION_RESULT_REJECTED,
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RedemptionOrderItem::class);
    }

    public function collectedConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_confirmed_by');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function cancellationHandledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancellation_handled_by');
    }
}
