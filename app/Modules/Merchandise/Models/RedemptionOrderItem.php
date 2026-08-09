<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Models\AuditableModel;
use Database\Factories\Modules\Merchandise\Models\RedemptionOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedemptionOrderItem extends AuditableModel
{
    /** @use HasFactory<RedemptionOrderItemFactory> */
    use HasFactory;

    protected $table = 'redemption_order_items';

    protected $fillable = [
        'redemption_order_id',
        'merchandise_variant_id',
        'merchandise_name',
        'variant_label',
        'gold_price_each',
        'line_total',
        'quantity',
    ];

    protected $casts = [
        'gold_price_each' => 'integer',
        'line_total' => 'integer',
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(RedemptionOrder::class, 'redemption_order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MerchandiseVariant::class, 'merchandise_variant_id');
    }
}
