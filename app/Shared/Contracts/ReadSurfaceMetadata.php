<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface ReadSurfaceMetadata
{
    public function freshness(): string;

    public function permissionScope(): string;

    /** @return array<string, string> */
    public function fieldOwnership(): array;
}
