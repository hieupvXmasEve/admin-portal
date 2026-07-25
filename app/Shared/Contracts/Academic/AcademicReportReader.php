<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\ReadSurfaceMetadata;

interface AcademicReportReader extends ReadSurfaceMetadata
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(array $filters): array;
}
