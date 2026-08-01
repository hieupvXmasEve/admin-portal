<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiseImage extends AuditableModel
{
    protected $table = 'merchandise_images';

    protected $fillable = [
        'merchandise_id',
        'path',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function merchandise(): BelongsTo
    {
        return $this->belongsTo(Merchandise::class);
    }
}
