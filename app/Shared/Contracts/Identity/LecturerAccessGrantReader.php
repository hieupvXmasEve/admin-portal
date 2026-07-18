<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

use App\Shared\Contracts\Identity\DTO\LecturerAccessGrant;

interface LecturerAccessGrantReader
{
    public function activeForUser(int $userId): ?LecturerAccessGrant;
}
