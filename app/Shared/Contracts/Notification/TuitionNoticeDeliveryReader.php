<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

use App\Shared\Contracts\Notification\DTO\TuitionNoticeDeliveryOutcome;
use App\Shared\Contracts\Notification\DTO\TuitionNoticeRenderedCopy;

interface TuitionNoticeDeliveryReader
{
    public function outcomeForContent(
        string $typeKey,
        string $contentHash,
        ?int $recipientUserId = null,
        ?string $recipientEmail = null,
    ): TuitionNoticeDeliveryOutcome;

    public function renderedCopy(int $messageId): ?TuitionNoticeRenderedCopy;
}
