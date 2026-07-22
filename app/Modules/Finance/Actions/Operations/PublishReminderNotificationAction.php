<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class PublishReminderNotificationAction
{
    /**
     * @param  array{
     *     type_key: string,
     *     aggregate_type: string,
     *     aggregate_id: int|string,
     *     campus_id: int,
     *     recipient_type: string,
     *     recipient_id: int,
     *     data: array<string, mixed>,
     * }  $data
     */
    public static function run(array $data): void
    {
        $event = new DomainEvent(
            name: 'finance.payment_reminder_requested',
            deduplicationKey: sprintf(
                'finance.payment_reminder_requested:%s:%s:%s:%d:%s',
                $data['aggregate_type'],
                $data['aggregate_id'],
                $data['recipient_type'],
                $data['recipient_id'],
                (string) Str::uuid(),
            ),
            occurredAt: CarbonImmutable::now(),
            aggregateType: $data['aggregate_type'],
            aggregateId: (string) $data['aggregate_id'],
            campusId: $data['campus_id'],
            actorUserId: null,
            payload: [
                'type_key' => $data['type_key'],
                'channels' => ['email'],
                'recipient_targets' => [[
                    'type' => $data['recipient_type'],
                    'id' => $data['recipient_id'],
                ]],
                'data' => [
                    'title' => 'Payment reminder',
                    'body' => 'Please complete your outstanding payment before the due date.',
                    ...$data['data'],
                ],
            ],
        );

        app(DomainEventPublisher::class)->publishAfterCommit($event);
    }
}
