<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

interface AvailableLecturerReader
{
    /** @return list<array{id: int, first_name: string, last_name: string, email: string, academic_rank: string|null}> */
    public function all(): array;
}
