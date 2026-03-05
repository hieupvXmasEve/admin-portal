<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Modules\Notification\Enums\NotificationOutboxStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationEventOutbox extends Model
{
    protected $table = 'notification_event_outbox';

    protected $fillable = [
        'event_id',
        'event_name',
        'event_version',
        'occurred_at',
        'aggregate_type',
        'aggregate_id',
        'campus_id',
        'actor_user_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'next_retry_at',
        'dispatched_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'status' => NotificationOutboxStatus::class,
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(NotificationMessage::class, 'event_id', 'event_id');
    }
}
