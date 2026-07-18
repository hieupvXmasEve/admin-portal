<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface CampusBuildingCountReader
{
    /**
     * @param  list<int>  $campusIds
     * @return array<int, int>
     */
    public function countByCampusIds(array $campusIds): array;
}
