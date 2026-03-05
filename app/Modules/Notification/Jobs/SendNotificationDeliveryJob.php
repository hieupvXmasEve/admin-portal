<?php

declare(strict_types=1);

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Channels\EmailChannelAdapter;
use App\Modules\Notification\Channels\RealtimeChannelAdapter;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Support\NotificationAuditLogger;
use App\Modules\Notification\Support\NotificationMetrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendNotificationDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly int $deliveryId,
    ) {
        $this->tries = (int) config('notification.delivery.max_attempts', 5);
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return array_values(array_map('intval', (array) config('notification.delivery.retry_seconds', [10, 30, 60, 300, 900])));
    }

    public function handle(
        EmailChannelAdapter $emailChannelAdapter,
        RealtimeChannelAdapter $realtimeChannelAdapter,
    ): void {
        $delivery = NotificationDelivery::query()->with('message')->findOrFail($this->deliveryId);
        if ($delivery->status === NotificationDeliveryStatus::Sent) {
            return;
        }

        $delivery->forceFill([
            'attempts' => (int) $delivery->attempts + 1,
            'queued_at' => now(),
        ])->save();

        $result = match ($delivery->channel->value) {
            'email' => $emailChannelAdapter->send($delivery),
            'realtime' => $realtimeChannelAdapter->send($delivery),
            default => throw new RuntimeException('Unsupported notification channel: '.$delivery->channel->value),
        };

        $delivery->forceFill([
            'status' => NotificationDeliveryStatus::Sent,
            'sent_at' => now(),
            'failed_at' => null,
            'last_error' => null,
            'next_retry_at' => null,
            'provider_message_id' => $result['provider_message_id'] ?? null,
            'email_log_id' => $result['email_log_id'] ?? null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);
        if (! $delivery) {
            return;
        }

        $delivery->forceFill([
            'status' => NotificationDeliveryStatus::Failed,
            'failed_at' => now(),
            'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            'next_retry_at' => null,
        ])->save();

        app(NotificationAuditLogger::class)->deliveryFailed([
            'delivery_id' => $delivery->id,
            'message_id' => $delivery->message_id,
            'channel' => $delivery->channel->value,
            'error' => $exception->getMessage(),
        ]);

        app(NotificationMetrics::class)->increment('notification_delivery_failed_total', [
            'channel' => $delivery->channel->value,
        ]);
    }
}
