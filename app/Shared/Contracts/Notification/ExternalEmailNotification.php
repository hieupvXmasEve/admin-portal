<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

/**
 * Text/HTML-only email intent for an address that is not an application user.
 * Attachments are intentionally deferred until their storage and retry contract
 * is designed.
 *
 * @param  array<int, string>  $recipientEmails
 */
final class ExternalEmailNotification
{
    public function __construct(
        public readonly array $recipientEmails,
        public readonly string $subject,
        public readonly string $html,
        public readonly ?int $campusId = null,
        public readonly ?int $actorUserId = null,
        public readonly string $aggregateType = 'external_email',
        public readonly ?string $aggregateId = null,
        public readonly string $typeKey = 'external_email',
        public readonly ?string $deduplicationKey = null,
    ) {}
}
