<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

interface ActiveLecturerReader
{
    /** @return list<array{id: int, first_name: string, last_name: string, display_name: string}> */
    public function all(): array;
}
