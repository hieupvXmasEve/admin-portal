<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Domain\Contracts\NotificationIntent;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationMessageStatus;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationMessage;

class PersistIntentAction
{
    /**
     * @param  array<int, int>  $recipientUserIds
     * @param  array<int, string>  $allowChannels
     * @param  array{rendered_subject?: string, rendered_html?: string, rendered_text?: string|null}  $renderedEmail
     * @return array<int, NotificationDelivery>
     */
    public function run(DomainEventEnvelope $event, NotificationIntent $intent, array $recipientUserIds, array $allowChannels, array $renderedEmail = []): array
    {
        $deliveries = [];

        foreach ($recipientUserIds as $recipientUserId) {
            $message = NotificationMessage::query()->updateOrCreate(
                [
                    'event_id' => $event->eventId,
                    'type_key' => $intent->typeKey,
                    'recipient_user_id' => $recipientUserId,
                ],
                [
                    'event_name' => $event->eventName,
                    'campus_id' => $event->campusId,
                    'actor_user_id' => $event->actorUserId,
                    'recipient_meta' => ['source' => 'resolved_user_id'],
                    'title' => (string) ($intent->data['title'] ?? $this->defaultTitle($intent->typeKey)),
                    'body' => (string) ($intent->data['body'] ?? ''),
                    'data' => $intent->data,
                    'status' => NotificationMessageStatus::Active,
                ]
            );

            foreach ($allowChannels as $channel) {
                $deliveryData = [
                    'status' => NotificationDeliveryStatus::Pending,
                    'queued_at' => now(),
                ];

                if ($channel === 'email' && isset($renderedEmail['rendered_subject'])) {
                    $deliveryData['rendered_subject'] = $renderedEmail['rendered_subject'];
                    $deliveryData['rendered_html'] = $renderedEmail['rendered_html'] ?? null;
                    $deliveryData['rendered_text'] = $renderedEmail['rendered_text'] ?? null;
                }

                $delivery = NotificationDelivery::query()->firstOrCreate(
                    [
                        'message_id' => $message->id,
                        'channel' => $channel,
                    ],
                    $deliveryData
                );

                if ($delivery->status === NotificationDeliveryStatus::Pending) {
                    $deliveries[] = $delivery;
                }
            }
        }

        return $deliveries;
    }

    private function defaultTitle(string $typeKey): string
    {
        return str_replace('_', ' ', ucfirst($typeKey));
    }
}
