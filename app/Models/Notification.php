<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted()
    {
        static::created(function ($notification) {
            // Check if broadcast channel is requested
            $channels = $notification->channels ?? [];
            if (is_array($channels) && in_array('broadcast', $channels)) {
                // Trigger realtime broadcast
                broadcast(new \App\Events\NotificationBroadcast($notification));
            }
        });
    }

    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'category',
        'title',
        'message',
        'data',
        'channels',
        'is_important',
        'expires_at',
        'read_at',
        'archived_at',
    ];

    protected $casts = [
        'category' => NotificationCategory::class,
        'data' => 'array',
        'channels' => 'array',
        'is_important' => 'boolean',
        'expires_at' => 'datetime',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    protected $attributes = [
        'is_important' => false,
        'channels' => '["database", "broadcast"]',
    ];

    // Relationships

    /**
     * Get the notifiable entity that the notification belongs to.
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread(Builder $query): Builder
    {
        // don't take event expired but get expired is null
        return $query->whereNull('read_at')->where(function ($query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeImportant(Builder $query): Builder
    {
        return $query->where('is_important', true);
    }

    public function scopeByCategory(Builder $query, NotificationCategory $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to filter expired notifications.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeForNotifiable(Builder $query, Model $model): Builder
    {
        return $query->where('notifiable_type', $model->getMorphClass())
            ->where('notifiable_id', $model->getKey());
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeOlderThan(Builder $query, int $days): Builder
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->notExpired();
    }

    // Accessors & Mutators

    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
// Methods

    public function markAsRead(): bool
    {
        if ($this->read_at) {
            return true;
        }

        return $this->update(['read_at' => now()]);
    }

    public function markAsUnread(): bool
    {
        if (!$this->read_at) {
            return true;
        }

        return $this->update(['read_at' => null]);
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        // Add computed attributes
        $array['is_read'] = $this->is_read;
        $array['is_expired'] = $this->is_expired;

        return $array;
    }
}
