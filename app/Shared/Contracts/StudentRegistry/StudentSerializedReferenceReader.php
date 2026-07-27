<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

interface StudentSerializedReferenceReader
{
    /** @return array<string, mixed>|null */
    public function findSerialized(int $studentId): ?array;

    /** @param list<int> $studentIds @return array<int, array<string, mixed>> */
    public function findManySerialized(array $studentIds): array;
}
