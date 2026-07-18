<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

interface LegacyDngCampusMappingWriter
{
    public function replace(int $campusId, ?string $dngCode): void;
}
