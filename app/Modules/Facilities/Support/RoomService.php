<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use App\Modules\Facilities\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoomService
{
    /**
     * Get paginated rooms with filters and campus scoping.
     */
    public function getPaginatedRooms(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Room::query()
            ->with(['campus', 'building'])
            ->forCampus(app('campus')->id);

        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get all rooms for the current campus.
     */
    public function getAllRooms(): Collection
    {
        return Room::query()
            ->with(['campus', 'building'])
            ->forCampus(app('campus')->id)
            ->get();
    }

    /**
     * Find a room by ID with campus scoping.
     */
    public function findRoom(int $id): ?Room
    {
        return Room::query()
            ->with(['campus', 'building'])
            ->forCampus(app('campus')->id)
            ->find($id);
    }

    /**
     * Create a new room.
     */
    public function createRoom(array $data): Room
    {
        return DB::transaction(function () use ($data) {
            // Ensure the room is associated with the current campus
            $data['campus_id'] = app('campus')->id;

            // Convert blocked_days to array if it's a string
            if (isset($data['blocked_days']) && is_string($data['blocked_days'])) {
                $data['blocked_days'] = json_decode($data['blocked_days'], true) ?? [];
            }

            return Room::create($data);
        });
    }

    /**
     * Update an existing room.
     */
    public function updateRoom(Room $room, array $data): Room
    {
        return DB::transaction(function () use ($room, $data) {
            // Prevent changing campus_id
            unset($data['campus_id']);

            // Convert blocked_days to array if it's a string
            if (isset($data['blocked_days']) && is_string($data['blocked_days'])) {
                $data['blocked_days'] = json_decode($data['blocked_days'], true) ?? [];
            }

            $room->update($data);

            return $room->fresh(['campus', 'building']);
        });
    }

    /**
     * Delete a room.
     */
    public function deleteRoom(Room $room): bool
    {
        return DB::transaction(function () use ($room) {
            return $room->delete();
        });
    }

    /**
     * Get rooms by type for the current campus.
     */
    public function getRoomsByType(string $type): Collection
    {
        return Room::query()
            ->with(['campus', 'building'])
            ->forCampus(app('campus')->id)
            ->ofType($type)
            ->get();
    }

    /**
     * Get available rooms for the current campus.
     */
    public function getAvailableRooms(): Collection
    {
        return Room::query()
            ->with(['campus', 'building'])
            ->forCampus(app('campus')->id)
            ->withStatus(Room::STATUS_AVAILABLE)
            ->bookable()
            ->get();
    }

    /**
     * Get rooms with minimum capacity for the current campus.
     */
    public function getRoomsWithMinimumCapacity(int $capacity): Collection
    {
        return Room::query()
            ->with(['campus', 'building'])
            ->forCampus(app('campus')->id)
            ->withMinimumCapacity($capacity)
            ->get();
    }

    /**
     * Get room statistics for the current campus.
     */
    public function getRoomStatistics(): array
    {
        $campusId = app('campus')->id;

        $totalRooms = Room::forCampus($campusId)->count();
        $availableRooms = Room::forCampus($campusId)->withStatus(Room::STATUS_AVAILABLE)->count();
        $bookableRooms = Room::forCampus($campusId)->bookable()->count();

        $roomsByType = Room::forCampus($campusId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $roomsByStatus = Room::forCampus($campusId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_rooms' => $totalRooms,
            'available_rooms' => $availableRooms,
            'bookable_rooms' => $bookableRooms,
            'by_type' => $roomsByType,
            'by_status' => $roomsByStatus,
        ];
    }

    /**
     * Apply filters to the rooms query.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhereHas('building', function ($buildingQuery) use ($search) {
                        $buildingQuery->where('name', 'like', $search)
                            ->orWhere('code', 'like', $search);
                    });
            });
        }

        if (! empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->withStatus($filters['status']);
        }

        if (! empty($filters['building_id'])) {
            $query->where('building_id', $filters['building_id']);
        }

        if (! empty($filters['floor'])) {
            $query->where('floor', $filters['floor']);
        }

        if (isset($filters['is_bookable']) && $filters['is_bookable'] !== '') {
            $query->where('is_bookable', (bool) $filters['is_bookable']);
        }

        if (isset($filters['requires_approval']) && $filters['requires_approval'] !== '') {
            $query->where('requires_approval', (bool) $filters['requires_approval']);
        }

        if (! empty($filters['min_capacity'])) {
            $query->withMinimumCapacity((int) $filters['min_capacity']);
        }

        if (! empty($filters['max_capacity'])) {
            $query->where('capacity', '<=', (int) $filters['max_capacity']);
        }

        // Sorting
        if (! empty($filters['sort'])) {
            $direction = $filters['direction'] ?? 'asc';
            $query->orderBy($filters['sort'], $direction);
        }
    }
}
