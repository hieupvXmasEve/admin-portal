<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use App\Models\Room;
use App\Shared\Contracts\Facilities\DTO\SpaceReference;
use App\Shared\Contracts\Facilities\SpaceReferenceReader;

class EloquentSpaceReferenceReader implements SpaceReferenceReader
{
    /** @return list<SpaceReference> */
    public function forCampus(?int $campusId): array
    {
        return Room::query()
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->orderBy('name')
            ->get(['id', 'campus_id', 'name', 'code', 'capacity'])
            ->map(fn (Room $room) => new SpaceReference(
                id: $room->id,
                campusId: $room->campus_id,
                name: $room->name,
                code: $room->code,
                capacity: $room->capacity,
            ))
            ->all();
    }
}
