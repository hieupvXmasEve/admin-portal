<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

interface LecturerTokenIssuer
{
    /** @return array{token: string, lecturer: object} */
    public function issue(int $lecturerId, string $deviceName, ?string $avatarUrl = null): array;
}
