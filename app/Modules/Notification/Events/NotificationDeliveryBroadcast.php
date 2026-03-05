<?php

declare(strict_types=1);

namespace App\Modules\Notification\Events;

use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationDeliveryBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly NotificationMessage $message,
    ) {}

    public function broadcastOn(): array
    {
        $campusId = $this->message->campus_id ?? 0;

        return [new PrivateChannel("notify.{$campusId}.{$this->message->recipient_user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'title' => $this->message->title,
            'message' => $this->message->body,
            'data' => $this->message->data,
            'type_key' => $this->message->type_key,
            'event_name' => $this->message->event_name,
            'created_at' => $this->message->created_at?->toISOString(),
        ];
    }
}
