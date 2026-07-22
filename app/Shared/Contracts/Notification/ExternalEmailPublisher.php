<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

interface ExternalEmailPublisher
{
    public function publishAfterCommit(ExternalEmailNotification $notification): string;
}
