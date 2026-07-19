<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

interface EmailContentResolver
{
    public function has(string $typeKey): bool;

    public function resolve(string $typeKey): EmailContentProvider;

    public function isConfiguredForCampus(string $typeKey, int $campusId): bool;
}
