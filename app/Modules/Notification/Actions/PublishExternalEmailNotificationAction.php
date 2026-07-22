<?php

declare(strict_types=1);

namespace App\Modules\Notification\Actions;

use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Notification\ExternalEmailNotification;
use App\Shared\Contracts\Notification\ExternalEmailPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mews\Purifier\Facades\Purifier;

class PublishExternalEmailNotificationAction implements ExternalEmailPublisher
{
    public function __construct(
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {}

    /**
     * Text/HTML-only email publication. Attachments intentionally remain on the
     * legacy path until storage retention and retry semantics are designed.
     */
    public function publishAfterCommit(ExternalEmailNotification $notification): string
    {
        $subject = trim($notification->subject);
        if ($subject === '' || preg_match('/[\r\n]/', $subject)) {
            throw new InvalidArgumentException('Email subject must be non-empty and must not contain line breaks.');
        }

        $recipients = collect($notification->recipientEmails)
            ->map(static fn (string $email): string => mb_strtolower(trim($email)))
            ->filter(static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        if ($recipients->isEmpty()) {
            throw new InvalidArgumentException('At least one valid recipient email is required.');
        }

        $deduplicationKey = $notification->deduplicationKey ?? sprintf(
            'notification.external_email_requested:%s',
            Str::uuid(),
        );
        $event = new DomainEvent(
            name: 'notification.external_email_requested',
            deduplicationKey: $deduplicationKey,
            occurredAt: CarbonImmutable::now(),
            aggregateType: $notification->aggregateType,
            aggregateId: $notification->aggregateId ?? $deduplicationKey,
            campusId: $notification->campusId,
            actorUserId: $notification->actorUserId,
            payload: [
                'type_key' => $notification->typeKey,
                'channels' => ['email'],
                'recipient_targets' => $recipients
                    ->map(static fn (string $email): array => ['type' => 'email', 'email' => $email])
                    ->all(),
                'rendered_email' => [
                    'rendered_subject' => $subject,
                    'rendered_html' => Purifier::clean($notification->html, 'email_body'),
                    'rendered_text' => null,
                ],
                'data' => [
                    'title' => $subject,
                    'body' => strip_tags($notification->html),
                ],
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);

        return $event->eventId();
    }
}
