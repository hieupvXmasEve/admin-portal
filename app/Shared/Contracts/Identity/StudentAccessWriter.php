<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

use App\Shared\Contracts\Identity\DTO\StudentAccessAccount;

interface StudentAccessWriter
{
    public function provision(int $campusId, string $fullName, string $email): StudentAccessAccount;

    public function revoke(int $accountId): void;
}
