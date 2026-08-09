<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Queries;

use App\Modules\Facilities\Models\Building;
use App\Modules\Facilities\Models\Room;

class GetRoomFilterOptionsQuery
{
    public function buildings(): array
    {
        return Building::forCampus(app('campus')->id)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(fn (Building $building) => [
                'id' => $building->id,
                'label' => $building->name.' ('.$building->code.')',
                'name' => $building->name,
                'code' => $building->code,
            ])
            ->all();
    }

    public function floors(): array
    {
        return Room::forCampus(app('campus')->id)
            ->distinct()
            ->pluck('floor')
            ->filter()
            ->sort()
            ->values()
            ->all();
    }
}
