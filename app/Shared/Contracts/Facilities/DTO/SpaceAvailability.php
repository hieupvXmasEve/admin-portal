<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Facilities\DTO;

final readonly class SpaceAvailability
{
    /** @param list<array<string, mixed>> $conflicts */
    public function __construct(
        public bool $available,
        public ?int $capacity,
        public array $conflicts,
    ) {}
}
