<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Models;

use App\Models\AuditableModel;
use Database\Factories\Modules\Merchandise\Models\MerchandiseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchandise extends AuditableModel
{
    /** @use HasFactory<MerchandiseFactory> */
    use HasFactory;

    protected $table = 'merchandise';

    protected $fillable = [
        'name',
        'description',
        'gold_price',
        'status',
    ];

    protected $casts = [
        'gold_price' => 'integer',
    ];

    /**
     * Statuses (backend allow-list — no DB enum).
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMING_SOON = 'coming_soon';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Allowed statuses.
     *
     * @var list<string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_COMING_SOON,
        self::STATUS_HIDDEN,
        self::STATUS_ARCHIVED,
    ];

    /**
     * Attached images, primary/lowest sort_order first.
     */
    public function images(): HasMany
    {
        return $this->hasMany(MerchandiseImage::class)->orderBy('sort_order');
    }

    /**
     * Per-campus stock variants.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MerchandiseVariant::class);
    }
}
