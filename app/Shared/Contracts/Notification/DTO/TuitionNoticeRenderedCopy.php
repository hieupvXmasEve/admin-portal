<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification\DTO;

final readonly class TuitionNoticeRenderedCopy
{
    public function __construct(
        public int $messageId,
        public string $typeKey,
        public int $campusId,
        public ?int $recipientUserId,
        public ?string $recipientEmail,
        public string $renderedHtml,
    ) {}
}
