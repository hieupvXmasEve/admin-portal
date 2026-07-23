<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Queries;

use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;

class ListRoomsQuery
{
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = Room::query()
            ->with(['campus', 'building'])
            ->forCampus(app('campus')->id);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($roomQuery) => $roomQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('building', fn ($buildingQuery) => $buildingQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")));
        }

        foreach (['type', 'status', 'building_id', 'floor'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        foreach (['is_bookable', 'requires_approval'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (! empty($filters['min_capacity'])) {
            $query->where('capacity', '>=', $filters['min_capacity']);
        }
        if (! empty($filters['max_capacity'])) {
            $query->where('capacity', '<=', $filters['max_capacity']);
        }

        if (! empty($filters['sort'])) {
            $query->orderBy($filters['sort'], $filters['direction'] ?? 'asc');
        }

        return $query->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }
}
