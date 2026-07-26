<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\StudentRegistry\DTO\StudentPortalToken;

interface StudentPortalTokenRefresher
{
    public function refresh(int $studentId, ?string $deviceName = null): StudentPortalToken;
}
