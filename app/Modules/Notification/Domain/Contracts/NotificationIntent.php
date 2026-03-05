<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Contracts;

final class NotificationIntent
{
    /**
     * @param  array<int, array{type:string,id:int}>  $recipientTargets
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $templateKeys
     */
    public function __construct(
        public readonly string $typeKey,
        public readonly array $recipientTargets,
        public readonly array $channels,
        public readonly array $data,
        public readonly array $templateKeys,
        public readonly string $priority = 'normal',
    ) {}
}
