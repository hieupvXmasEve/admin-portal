<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity\DTO;

final readonly class StaffActorReference
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
