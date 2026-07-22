<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity\DTO;

final readonly class ImpersonationAdministrator
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $ipAddress,
        public ?string $userAgent,
        public ?string $deviceName,
        public ?string $purpose,
    ) {}
}
