<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MerchandiseVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseVariant extends AuditableModel
{
    /** @use HasFactory<MerchandiseVariantFactory> */
    use HasFactory;

    protected $table = 'merchandise_variants';

    protected $fillable = [
        'merchandise_id',
        'campus_id',
        'color',
        'size',
        'sku',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function merchandise(): BelongsTo
    {
        return $this->belongsTo(Merchandise::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
