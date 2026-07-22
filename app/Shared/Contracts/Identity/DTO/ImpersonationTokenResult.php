<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity\DTO;

final readonly class ImpersonationTokenResult
{
    /** @param array<string, mixed> $payload */
    public function __construct(public array $payload) {}
}
