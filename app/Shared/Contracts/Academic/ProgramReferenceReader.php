<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface ProgramReferenceReader
{
    /** @return list<array{id: int, code: string, name: string}> */
    public function all(): array;

    /** @return array{id: int, code: string, name: string}|null */
    public function find(int $programId): ?array;
}
