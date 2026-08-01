<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends AuditableModel
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'merchandise_variant_id',
        'change',
        'quantity_before',
        'quantity_after',
        'type',
        'redemption_order_id',
        'performed_by',
        'note',
    ];

    protected $casts = [
        'change' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'redemption_order_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Append-only ledger — only tracks created_at, mirrors GoldTransaction.
     */
    public $timestamps = false;

    /**
     * Movement types (backend allow-list — no DB enum).
     */
    public const TYPE_STOCK_IN = 'stock_in';

    public const TYPE_REDEMPTION_OUT = 'redemption_out';

    public const TYPE_REDEMPTION_REFUND = 'redemption_refund';

    public const TYPE_MANUAL_INCREASE = 'manual_increase';

    public const TYPE_MANUAL_DECREASE = 'manual_decrease';

    public const TYPE_DAMAGED = 'damaged';

    public const TYPE_LOST = 'lost';

    /**
     * Allowed movement types.
     *
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_STOCK_IN,
        self::TYPE_REDEMPTION_OUT,
        self::TYPE_REDEMPTION_REFUND,
        self::TYPE_MANUAL_INCREASE,
        self::TYPE_MANUAL_DECREASE,
        self::TYPE_DAMAGED,
        self::TYPE_LOST,
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MerchandiseVariant::class, 'merchandise_variant_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
