<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\Building;
use App\Shared\Contracts\Academic\CampusBuildingCountReader as CampusBuildingCountReaderContract;

class CampusBuildingCountReader implements CampusBuildingCountReaderContract
{
    /**
     * @param  list<int>  $campusIds
     * @return array<int, int>
     */
    public function countByCampusIds(array $campusIds): array
    {
        if ($campusIds === []) {
            return [];
        }

        return Building::query()
            ->whereIn('campus_id', $campusIds)
            ->selectRaw('campus_id, count(*) as aggregate')
            ->groupBy('campus_id')
            ->pluck('aggregate', 'campus_id')
            ->mapWithKeys(fn (int|string $count, int|string $campusId): array => [
                (int) $campusId => (int) $count,
            ])
            ->all();
    }
}
