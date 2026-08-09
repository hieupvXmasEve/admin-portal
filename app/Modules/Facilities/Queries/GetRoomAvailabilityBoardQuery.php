<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Queries;

use App\Modules\Facilities\Models\Room;
use App\Modules\Facilities\Models\RoomBooking;
use App\Modules\Facilities\Support\RoomBookingSlotValidator;
use App\Shared\Contracts\Academic\AcademicSpaceOccupancyReader;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GetRoomAvailabilityBoardQuery
{
    public function __construct(
        private readonly RoomBookingSlotValidator $slotValidator,
        private readonly AcademicSpaceOccupancyReader $academicSpaceOccupancyReader,
    ) {}

    public function handle(array $filters): array
    {
        $campusId = (int) $filters['campus_id'];
        $startDate = Carbon::parse($filters['start_date'])->format('Y-m-d');
        $endDate = Carbon::parse($filters['end_date'])->format('Y-m-d');
        $startTime = substr((string) ($filters['start_time'] ?? '07:00'), 0, 5);
        $endTime = substr((string) ($filters['end_time'] ?? '20:00'), 0, 5);

        $rooms = Room::query()
            ->with(['building'])
            ->forCampus($campusId)
            ->when(! empty($filters['building_id']), fn ($query) => $query->where('building_id', (int) $filters['building_id']))
            ->when(! empty($filters['room_id']), fn ($query) => $query->where('id', (int) $filters['room_id']))
            ->orderBy('building_id')
            ->orderBy('name')
            ->get();

        $roomIds = $rooms->pluck('id')->all();
        $dates = collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn (Carbon $date) => $date->format('Y-m-d'))
            ->values()
            ->all();

        $bookingEvents = RoomBooking::query()
            ->with(['bookedBy'])
            ->whereIn('room_id', $roomIds)
            ->whereBetween('booking_date', [$startDate, $endDate])
            ->activeBookings()
            ->get()
            ->map(fn (RoomBooking $booking) => [
                'type' => 'room_booking',
                'id' => $booking->id,
                'booking_id' => $booking->id,
                'room_id' => $booking->room_id,
                'date' => $booking->booking_date->format('Y-m-d'),
                'title' => $booking->title,
                'start_time' => $this->formatTime($booking->start_time),
                'end_time' => $this->formatTime($booking->end_time),
                'status' => $booking->status,
                'booker_name' => $booking->booker_name,
            ]);

        $mirroredBookingIds = $bookingEvents
            ->pluck('booking_id')
            ->filter()
            ->values()
            ->all();

        $academicEvents = collect($this->academicSpaceOccupancyReader->forRooms($roomIds, $startDate, $endDate))
            ->reject(fn ($occupancy) => $occupancy->type === 'exam_room_slot'
                && $occupancy->reservationId !== null
                && in_array($occupancy->reservationId, $mirroredBookingIds, true))
            ->map(fn ($occupancy) => [
                'type' => $occupancy->type,
                'id' => $occupancy->sourceId,
                $occupancy->type.'_id' => $occupancy->sourceId,
                'room_id' => $occupancy->roomId,
                'date' => $occupancy->date,
                'title' => $occupancy->title,
                'start_time' => $occupancy->startTime,
                'end_time' => $occupancy->endTime,
                'status' => $occupancy->status,
                'is_editable' => false,
            ]);

        $eventsByRoomDate = $bookingEvents
            ->concat($academicEvents)
            ->filter(fn (array $event) => $event['end_time'] > $startTime && $event['start_time'] < $endTime)
            ->groupBy(fn (array $event) => $event['room_id'].'|'.$event['date']);

        $roomRows = $rooms->map(function (Room $room) use ($dates, $eventsByRoomDate, $startTime, $endTime) {
            $days = collect($dates)->map(function (string $date) use ($room, $eventsByRoomDate, $startTime, $endTime) {
                $events = $eventsByRoomDate->get($room->id.'|'.$date, collect())
                    ->sortBy('start_time')
                    ->values()
                    ->all();

                $effectiveWindow = $this->slotValidator->effectiveWindow($room, $startTime, $endTime);
                $isBlockedDay = $room->blocked_days && in_array(Carbon::parse($date)->format('l'), $room->blocked_days, true);
                $isUsable = $room->is_bookable && $room->status === Room::STATUS_AVAILABLE && ! $isBlockedDay && $effectiveWindow['start_time'] < $effectiveWindow['end_time'];

                return [
                    'date' => $date,
                    'events' => $events,
                    'free_windows' => $isUsable ? $this->freeWindows($effectiveWindow['start_time'], $effectiveWindow['end_time'], $events) : [],
                    'is_bookable' => $isUsable,
                    'blocked_reason' => $this->blockedReason($room, $isBlockedDay),
                ];
            })->values()->all();

            return [
                'id' => $room->id,
                'name' => $room->name,
                'code' => $room->code,
                'capacity' => $room->capacity,
                'status' => $room->status,
                'is_bookable' => $room->is_bookable,
                'building' => $room->building ? [
                    'id' => $room->building->id,
                    'name' => $room->building->name,
                    'code' => $room->building->code,
                ] : null,
                'available_from' => $this->formatTime($room->available_from),
                'available_until' => $this->formatTime($room->available_until),
                'days' => $days,
            ];
        })->values()->all();

        return [
            'dates' => $dates,
            'rooms' => $roomRows,
            'summary' => [
                'rooms' => count($roomRows),
                'dates' => count($dates),
                'busy_events' => $bookingEvents->count() + $academicEvents->count(),
            ],
        ];
    }

    private function freeWindows(string $windowStart, string $windowEnd, array $events): array
    {
        $free = [];
        $cursor = $this->timeToMinutes($windowStart);
        $windowEndMinutes = $this->timeToMinutes($windowEnd);

        foreach ($events as $event) {
            $eventStart = max($this->timeToMinutes($event['start_time']), $cursor);
            $eventEnd = min($this->timeToMinutes($event['end_time']), $windowEndMinutes);

            if ($eventStart > $cursor) {
                $free[] = [
                    'start_time' => $this->minutesToTime($cursor),
                    'end_time' => $this->minutesToTime($eventStart),
                ];
            }

            if ($eventEnd > $cursor) {
                $cursor = $eventEnd;
            }
        }

        if ($cursor < $windowEndMinutes) {
            $free[] = [
                'start_time' => $this->minutesToTime($cursor),
                'end_time' => $this->minutesToTime($windowEndMinutes),
            ];
        }

        return $free;
    }

    private function blockedReason(Room $room, bool $isBlockedDay): ?string
    {
        if (! $room->is_bookable) {
            return 'Room is not bookable';
        }

        if ($room->status !== Room::STATUS_AVAILABLE) {
            return 'Room status is '.$room->status;
        }

        if ($isBlockedDay) {
            return 'Blocked day';
        }

        return null;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hours * 60 + $minutes;
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
