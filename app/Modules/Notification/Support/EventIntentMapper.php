<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use App\Modules\Notification\Domain\Contracts\NotificationIntent;

class EventIntentMapper
{
    /**
     * @return array<int, NotificationIntent>
     */
    public function map(DomainEventEnvelope $event): array
    {
        $typeKey = $this->resolveTypeKey($event);
        if ($typeKey === null) {
            return [];
        }

        $channels = $this->resolveChannels($event->payload);
        $targets = $this->resolveTargets($event);

        if ($targets === []) {
            return [];
        }

        $templateKeys = [
            'email' => sprintf('%s_email_v1', $typeKey),
            'realtime' => sprintf('%s_realtime_v1', $typeKey),
        ];

        return [
            new NotificationIntent(
                typeKey: $typeKey,
                recipientTargets: $targets,
                channels: $channels,
                data: (array) ($event->payload['data'] ?? $event->payload),
                templateKeys: (array) ($event->payload['template_keys'] ?? $templateKeys),
                priority: (string) ($event->payload['priority'] ?? 'normal')
            ),
        ];
    }

    private function resolveTypeKey(DomainEventEnvelope $event): ?string
    {
        if (isset($event->payload['type_key']) && is_string($event->payload['type_key'])) {
            return $event->payload['type_key'];
        }

        return match ($event->eventName) {
            'finance.invoice_paid' => 'invoice_paid',
            'academic.enrollment_confirmed' => 'enrollment_confirmed',
            'manual.notification_sent' => 'manual_notification',
            'query.ticket_submitted' => 'query_submitted',
            'query.reply_created' => 'query_reply_created',
            'query.staff_reply_created' => 'query_staff_reply',
            'query.assigned' => 'query_assigned',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function resolveChannels(array $payload): array
    {
        $channels = $payload['channels'] ?? ['email', 'realtime'];
        if (! is_array($channels)) {
            return ['email', 'realtime'];
        }

        return array_values(array_filter(array_map('strval', $channels)));
    }

    /**
     * @return array<int, array{type:string,id:int}>
     */
    private function resolveTargets(DomainEventEnvelope $event): array
    {
        $targets = $event->payload['recipient_targets'] ?? [];
        if (is_array($targets) && $targets !== []) {
            return array_values(array_filter(array_map(function ($target) {
                if (! is_array($target)) {
                    return null;
                }

                $type = (string) ($target['type'] ?? '');
                $id = (int) ($target['id'] ?? 0);
                if ($type === '' || $id <= 0) {
                    return null;
                }

                return ['type' => $type, 'id' => $id];
            }, $targets)));
        }

        if ($event->eventName === 'finance.invoice_paid' && isset($event->payload['student_id'])) {
            return [['type' => 'student', 'id' => (int) $event->payload['student_id']]];
        }

        if ($event->eventName === 'academic.enrollment_confirmed' && isset($event->payload['student_id'])) {
            return [['type' => 'student', 'id' => (int) $event->payload['student_id']]];
        }

        return [];
    }
}
