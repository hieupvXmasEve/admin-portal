<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Platform;

use App\Shared\Contracts\ReadSurfaceMetadata;

interface StaffDashboardChartReader extends ReadSurfaceMetadata
{
    /** @return array<string, mixed> */
    public function studentDistributionForCampus(?int $campusId): array;

    /** @return array<string, mixed> */
    public function enrollmentGrowthForCampus(?int $campusId): array;

    /** @return array<string, mixed> */
    public function academicStandingForCampus(?int $campusId): array;

    /** @return array<string, mixed> */
    public function graduationRateForCampus(?int $campusId): array;
}
