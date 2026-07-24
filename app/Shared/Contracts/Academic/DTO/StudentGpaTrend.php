<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentGpaTrend
{
    /** @param array<string, mixed> $payload */
    public function __construct(private array $payload) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
