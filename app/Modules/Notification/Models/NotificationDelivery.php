<?php

declare(strict_types=1);

namespace App\Modules\Notification\Models;

use App\Models\EmailLog;
use App\Modules\Notification\Enums\NotificationDeliveryChannel;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $table = 'notification_deliveries';

    protected $fillable = [
        'message_id',
        'channel',
        'status',
        'attempts',
        'provider_message_id',
        'email_log_id',
        'last_error',
        'rendered_subject',
        'rendered_html',
        'rendered_text',
        'queued_at',
        'sent_at',
        'failed_at',
        'next_retry_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'status' => NotificationDeliveryStatus::class,
        'channel' => NotificationDeliveryChannel::class,
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(NotificationMessage::class, 'message_id');
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
