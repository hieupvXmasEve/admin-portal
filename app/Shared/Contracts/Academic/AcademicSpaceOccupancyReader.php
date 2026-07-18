<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\AcademicSpaceOccupancy;

interface AcademicSpaceOccupancyReader
{
    /**
     * @param  list<int>  $roomIds
     * @return list<AcademicSpaceOccupancy>
     */
    public function forRooms(array $roomIds, string $startDate, string $endDate): array;
}
