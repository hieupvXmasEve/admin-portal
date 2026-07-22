<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

interface StudentProfileWriter
{
    /** @param array<string, mixed> $attributes */
    public function update(int $studentId, array $attributes): bool;
}
