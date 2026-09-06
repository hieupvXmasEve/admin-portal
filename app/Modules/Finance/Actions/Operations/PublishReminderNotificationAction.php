<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
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
     *     recipient_email?: string,
     *     data: array<string, mixed>,
     * }  $data
     */
    public static function run(array $data): void
    {
        [$deduplicationKey, $payloadData] = self::deduplicationKeyAndPayload($data);
        $isTuitionNotice = self::isTuitionNotice($data['type_key']);

        $event = new DomainEvent(
            name: 'finance.payment_reminder_requested',
            deduplicationKey: $deduplicationKey,
            occurredAt: CarbonImmutable::now(),
            aggregateType: $data['aggregate_type'],
            aggregateId: (string) $data['aggregate_id'],
            campusId: $data['campus_id'],
            actorUserId: null,
            payload: [
                'type_key' => $data['type_key'],
                'channels' => ['email'],
                'recipient_targets' => [self::recipientTarget($data)],
                'data' => $isTuitionNotice
                    ? $payloadData
                    : [
                        'title' => 'Payment reminder',
                        'body' => 'Please complete your outstanding payment before the due date.',
                        ...$payloadData,
                    ],
            ],
        );

        app(DomainEventPublisher::class)->publishAfterCommit($event);
    }

    /**
     * @param  array{
     *     type_key: string,
     *     aggregate_type: string,
     *     aggregate_id: int|string,
     *     recipient_type: string,
     *     recipient_id: int,
     *     data: array<string, mixed>,
     * }  $data
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function deduplicationKeyAndPayload(array $data): array
    {
        $typeKey = $data['type_key'];

        if (! self::isTuitionNotice($typeKey)) {
            return [
                sprintf(
                    'finance.payment_reminder_requested:%s:%s:%s:%d:%s',
                    $data['aggregate_type'],
                    $data['aggregate_id'],
                    $data['recipient_type'],
                    $data['recipient_id'],
                    (string) Str::uuid(),
                ),
                $data['data'],
            ];
        }

        $payload = $data['data'];
        $retryNonce = (string) ($payload['retry_nonce'] ?? '0');
        unset($payload['retry_nonce'], $payload['content_hash']);
        $contentHash = hash(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
        $payload['content_hash'] = $contentHash;
        if ($retryNonce !== '0') {
            $payload['retry_nonce'] = $retryNonce;
        }

        return [
            sprintf(
                'finance.payment_reminder_requested:%s:%s:%s:%s:%d:%s:%s',
                $typeKey,
                $data['aggregate_type'],
                $data['aggregate_id'],
                $data['recipient_type'],
                $data['recipient_id'],
                $contentHash,
                $retryNonce,
            ),
            $payload,
        ];
    }

    /**
     * @param  array{recipient_type: string, recipient_id: int, recipient_email?: string}  $data
     * @return array{type: string, id?: int, email?: string}
     */
    private static function recipientTarget(array $data): array
    {
        if ($data['recipient_type'] === 'email') {
            return [
                'type' => 'email',
                'email' => (string) ($data['recipient_email'] ?? ''),
            ];
        }

        return [
            'type' => $data['recipient_type'],
            'id' => $data['recipient_id'],
        ];
    }

    private static function isTuitionNotice(string $typeKey): bool
    {
        return in_array($typeKey, [
            NotificationTemplateTypeKey::TuitionNotice->value,
            NotificationTemplateTypeKey::ParentTuitionNotice->value,
        ], true);
    }
}
