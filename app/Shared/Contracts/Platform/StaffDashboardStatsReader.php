<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Platform;

use App\Shared\Contracts\ReadSurfaceMetadata;

interface StaffDashboardStatsReader extends ReadSurfaceMetadata
{
    /** @return array<string, mixed> */
    public function statsForCampus(?int $campusId): array;

    /** @return list<array<string, mixed>> */
    public function alertsForCampus(?int $campusId): array;
}
