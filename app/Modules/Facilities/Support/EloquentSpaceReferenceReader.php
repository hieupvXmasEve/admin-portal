<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use App\Modules\Facilities\Models\Room;
use App\Shared\Contracts\Facilities\DTO\SpaceReference;
use App\Shared\Contracts\Facilities\SpaceReferenceReader;
use Illuminate\Database\Eloquent\Builder;

class EloquentSpaceReferenceReader implements SpaceReferenceReader
{
    /** @return list<SpaceReference> */
    public function forCampus(?int $campusId): array
    {
        return $this->references(
            Room::query()->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
        );
    }

    /** @return list<SpaceReference> */
    public function bookableForCampus(int $campusId): array
    {
        return $this->references(
            Room::query()
                ->forCampus($campusId)
                ->bookable()
                ->withStatus(Room::STATUS_AVAILABLE)
        );
    }

    /**
     * @param  Builder<Room>  $query
     * @return list<SpaceReference>
     */
    private function references(Builder $query): array
    {
        return $query
            ->with('building:id,name,code')
            ->orderBy('name')
            // building_id must be selected or the belongsTo cannot match and
            // `building` silently resolves to null for every row.
            ->get(['id', 'campus_id', 'building_id', 'name', 'code', 'capacity', 'type', 'status', 'is_bookable'])
            ->map(fn (Room $room) => new SpaceReference(
                id: $room->id,
                campusId: $room->campus_id,
                name: $room->name,
                code: $room->code,
                capacity: $room->capacity,
                type: $room->type,
                status: $room->status,
                isBookable: (bool) $room->is_bookable,
                building: $room->building === null ? null : [
                    'id' => (int) $room->building->id,
                    'name' => (string) $room->building->name,
                    'code' => (string) $room->building->code,
                ],
            ))
            ->all();
    }
}
