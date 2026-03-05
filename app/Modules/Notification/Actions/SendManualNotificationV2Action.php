<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SendManualNotificationV2Action
{
    public function __construct(
        private PublishDomainEventAction $publishAction
    ) {}

    /**
     * @param array{
     *   campus_id: int,
     *   notifiable_type: string,
     *   notifiable_ids: array<int>,
     *   title: string,
     *   message: string,
     *   category: string,
     *   is_important?: bool,
     *   action_url?: string|null,
     *   action_text?: string|null,
     *   actor_user_id?: int|null,
     * } $data
     */
    public function run(array $data): string
    {
        $eventId = (string) Str::uuid();
        $actorUserId = $data['actor_user_id'] ?? Auth::id();

        $envelope = new DomainEventEnvelope(
            eventId: $eventId,
            eventName: 'manual.notification_sent',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'manual_notification',
            aggregateId: $eventId,
            campusId: (int) $data['campus_id'],
            actorUserId: $actorUserId,
            payload: [
                'type_key' => 'manual_notification',
                'recipient_targets' => $this->buildTargets(
                    (string) $data['notifiable_type'],
                    (array) $data['notifiable_ids']
                ),
                'channels' => ['realtime'],
                'data' => [
                    'title' => (string) $data['title'],
                    'body' => (string) $data['message'],
                    'category' => (string) $data['category'],
                    'is_important' => (bool) ($data['is_important'] ?? false),
                    'action_url' => $data['action_url'] ?? null,
                    'action_text' => $data['action_text'] ?? null,
                ],
            ],
        );

        $this->publishAction->run($envelope);

        return $eventId;
    }

    /**
     * @param  array<int>  $ids
     * @return array<int, array{type: string, id: int}>
     */
    private function buildTargets(string $type, array $ids): array
    {
        $validIds = array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0
        );

        return array_map(
            fn (int $id): array => ['type' => $type, 'id' => $id],
            array_values($validIds)
        );
    }
}
