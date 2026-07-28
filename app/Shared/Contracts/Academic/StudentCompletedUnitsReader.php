<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\ReadSurfaceMetadata;
use Illuminate\Support\Collection;

interface StudentCompletedUnitsReader extends ReadSurfaceMetadata
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(array $filters): array;

    /** @param array<string, mixed> $filters @return Collection<int, array<string, mixed>> */
    public function handleExport(array $filters): Collection;
}
