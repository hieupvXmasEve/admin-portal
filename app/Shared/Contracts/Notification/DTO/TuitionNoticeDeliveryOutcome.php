<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification\DTO;

final readonly class TuitionNoticeDeliveryOutcome
{
    public function __construct(
        public bool $exists,
        public bool $blocksResend,
        public ?int $failedDeliveryId,
    ) {}
}
