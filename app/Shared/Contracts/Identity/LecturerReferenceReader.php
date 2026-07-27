<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity;

interface LecturerReferenceReader
{
    /** @return array<string, mixed>|null */
    public function find(int $lecturerId): ?array;

    /** @param list<int> $lecturerIds @return array<int, array<string, mixed>> */
    public function findMany(array $lecturerIds): array;
}
