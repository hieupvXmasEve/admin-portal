<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Queries;

use App\Modules\Facilities\Models\Room;
use App\Modules\Facilities\Support\RoomBookingSlotValidator;

class PreviewRoomBookingSeriesAvailabilityQuery
{
    public function __construct(
        private readonly RoomBookingSlotValidator $slotValidator
    ) {}

    public function handle(int $roomId, array $occurrences, ?int $campusId = null, ?int $excludeBookingId = null): array
    {
        $room = Room::with(['building', 'campus'])
            ->when($campusId !== null, fn ($query) => $query->forCampus($campusId))
            ->findOrFail($roomId);

        $rows = collect($occurrences)
            ->map(function (array $occurrence, int $index) use ($room, $campusId, $excludeBookingId) {
                $conflicts = [];

                try {
                    $this->slotValidator->validateRoomAndSlot(
                        $room,
                        $occurrence['booking_date'],
                        $occurrence['start_time'],
                        $occurrence['end_time'],
                        $campusId
                    );

                    $conflicts = $this->slotValidator->conflictsFor(
                        $room->id,
                        $occurrence['booking_date'],
                        $occurrence['start_time'],
                        $occurrence['end_time'],
                        $excludeBookingId
                    );
                } catch (\InvalidArgumentException $exception) {
                    $conflicts[] = [
                        'type' => 'validation',
                        'id' => 'validation-'.$index,
                        'title' => $exception->getMessage(),
                        'booking_date' => $occurrence['booking_date'],
                        'start_time' => $occurrence['start_time'],
                        'end_time' => $occurrence['end_time'],
                        'status' => 'blocked',
                    ];
                }

                return [
                    ...$occurrence,
                    'available' => count($conflicts) === 0,
                    'conflicts' => $conflicts,
                ];
            })
            ->values()
            ->all();

        $conflicts = collect($rows)
            ->flatMap(fn (array $row) => $row['conflicts'])
            ->values()
            ->all();

        return [
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'code' => $room->code,
                'building' => $room->building?->name,
            ],
            'occurrences' => $rows,
            'has_conflicts' => count($conflicts) > 0,
            'conflicts' => $conflicts,
            'summary' => [
                'total' => count($rows),
                'available' => collect($rows)->where('available', true)->count(),
                'conflicting' => collect($rows)->where('available', false)->count(),
            ],
        ];
    }
}
