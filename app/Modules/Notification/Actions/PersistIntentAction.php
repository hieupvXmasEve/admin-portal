<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Domain\Contracts\NotificationIntent;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationMessageStatus;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationMessage;
use App\Modules\Notification\Support\NotificationPreferenceResolver;

class PersistIntentAction
{
    public function __construct(
        private readonly NotificationPreferenceResolver $preferenceResolver,
    ) {}

    /**
     * @param  array<int, array{key:string,user_id:int|null,email:string|null}>  $recipients
     * @param  array<int, string>  $allowChannels
     * @param  array{rendered_subject?: string, rendered_html?: string, rendered_text?: string|null}  $renderedEmail
     * @return array<int, NotificationDelivery>
     */
    public function run(DomainEventEnvelope $event, NotificationIntent $intent, array $recipients, array $allowChannels, array $renderedEmail = []): array
    {
        $deliveries = [];

        foreach ($recipients as $recipient) {
            $recipientUserId = $recipient['user_id'];
            $recipientEmail = $recipient['email'];
            $message = NotificationMessage::query()->updateOrCreate(
                [
                    'event_id' => $event->eventId,
                    'type_key' => $intent->typeKey,
                    'recipient_key' => $recipient['key'],
                ],
                [
                    'event_name' => $event->eventName,
                    'campus_id' => $event->campusId,
                    'actor_user_id' => $event->actorUserId,
                    'recipient_user_id' => $recipientUserId,
                    'recipient_email' => $recipientEmail,
                    'recipient_meta' => ['source' => $recipientUserId === null ? 'external_email' : 'resolved_user_id'],
                    'title' => (string) ($intent->data['title'] ?? $this->defaultTitle($intent->typeKey)),
                    'body' => (string) ($intent->data['body'] ?? ''),
                    'data' => $intent->data,
                    'status' => NotificationMessageStatus::Active,
                ]
            );

            $recipientChannels = $recipientUserId === null
                ? array_values(array_intersect($allowChannels, ['email']))
                : $allowChannels;

            foreach ($recipientChannels as $channel) {
                $isAllowed = $recipientUserId === null
                    || $this->preferenceResolver->allows($recipientUserId, $intent->typeKey, $channel);
                $deliveryData = [
                    'status' => $isAllowed ? NotificationDeliveryStatus::Pending : NotificationDeliveryStatus::Skipped,
                    'queued_at' => now(),
                    'last_error' => $isAllowed ? null : 'suppressed_by_preference',
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

                if ($isAllowed && $delivery->status === NotificationDeliveryStatus::Pending) {
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
