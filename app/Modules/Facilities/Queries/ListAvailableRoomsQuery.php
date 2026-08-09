<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Queries;

use App\Modules\Facilities\Models\Room;
use Illuminate\Support\Collection;

class ListAvailableRoomsQuery
{
    public function handle(array $filters): Collection
    {
        $query = Room::query()
            ->with(['campus', 'building'])
            ->forCampus(app('campus')->id);

        if (isset($filters['status'])) {
            $query->withStatus($filters['status']);
        } else {
            $query->withStatus(Room::STATUS_AVAILABLE);
        }
        if (isset($filters['is_bookable'])) {
            $query->where('is_bookable', filter_var($filters['is_bookable'], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($filters['type'])) {
            $query->ofType($filters['type']);
        }
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($roomQuery) => $roomQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('building', fn ($buildingQuery) => $buildingQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")));
        }

        return $query->orderBy('name')
            ->limit((int) ($filters['limit'] ?? 50))
            ->get();
    }
}
