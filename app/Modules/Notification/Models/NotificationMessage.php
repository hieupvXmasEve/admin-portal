<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Models\User;
use App\Modules\Notification\Enums\NotificationMessageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationMessage extends Model
{
    protected $table = 'notification_messages';

    protected $fillable = [
        'event_id',
        'event_name',
        'type_key',
        'campus_id',
        'recipient_user_id',
        'recipient_key',
        'recipient_email',
        'actor_user_id',
        'recipient_meta',
        'title',
        'body',
        'data',
        'status',
        'read_at',
        'archived_at',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'recipient_meta' => 'array',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'expires_at' => 'datetime',
        'status' => NotificationMessageStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (NotificationMessage $message): void {
            if ($message->recipient_key !== null) {
                return;
            }

            if ($message->recipient_user_id !== null) {
                $message->recipient_key = 'user:'.$message->recipient_user_id;

                return;
            }

            if ($message->recipient_email !== null) {
                $message->recipient_key = 'email:'.mb_strtolower(trim($message->recipient_email));
            }
        });
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'message_id');
    }
}
