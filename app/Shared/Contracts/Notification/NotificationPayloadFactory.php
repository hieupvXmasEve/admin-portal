<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

interface NotificationPayloadFactory
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function build(string $typeKey, array $data, string $platform = 'web'): array;
}
