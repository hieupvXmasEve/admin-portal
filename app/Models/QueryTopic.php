<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueryTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'is_active',
        'order_index',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the tickets for this topic.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueryTicket::class, 'topic_id');
    }

    /**
     * Scope to get active topics.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get topics ordered by index.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index');
    }
}
