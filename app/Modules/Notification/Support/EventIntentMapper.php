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

        $channels = $this->resolveChannels($event);
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

        if ($event->eventName === 'academic.warning_sent') {
            $warningType = $event->payload['warning_type'] ?? null;

            return is_string($warningType) && $warningType !== '' ? $warningType : null;
        }

        return match ($event->eventName) {
            'finance.invoice_paid' => 'invoice_paid',
            'finance.dng_payment_received' => 'dng_payment_received',
            'finance.dng_payment_allocated' => 'dng_payment_allocated',
            'academic.enrollment_confirmed' => 'enrollment_confirmed',
            'academic.course_completed' => 'course_completed',
            'academic.course_score_updated' => 'course_score_updated',
            'academic.egc_course_completed' => 'egc_course_completed',
            'academic.egc_program_completed' => 'egc_program_completed',
            'academic.course_stage_changed' => 'course_stage_changed',
            'manual.notification_sent' => 'manual_notification',
            'query.ticket_submitted' => 'query_submitted',
            'query.reply_created' => 'query_reply_created',
            'query.staff_reply_created' => 'query_staff_reply',
            'query.assigned' => 'query_assigned',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function resolveChannels(DomainEventEnvelope $event): array
    {
        $channels = $event->payload['channels']
            ?? $event->payload['requested_channels']
            ?? (str_starts_with($event->eventName, 'academic.') ? ['realtime'] : ['email', 'realtime']);
        if (! is_array($channels)) {
            return str_starts_with($event->eventName, 'academic.') ? ['realtime'] : ['email', 'realtime'];
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

        if (str_starts_with($event->eventName, 'academic.') && isset($event->payload['student_id'])) {
            return [['type' => 'student', 'id' => (int) $event->payload['student_id']]];
        }

        return [];
    }
}
