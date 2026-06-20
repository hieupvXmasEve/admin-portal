<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Queries;

use App\Models\ClassSession;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Modules\Facilities\Support\RoomBookingSlotValidator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GetRoomAvailabilityBoardQuery
{
    public function __construct(
        private readonly RoomBookingSlotValidator $slotValidator
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

        $classEvents = ClassSession::query()
            ->with(['courseOffering.unit', 'lecture'])
            ->whereIn('room_id', $roomIds)
            ->whereBetween('session_date', [$startDate, $endDate])
            ->whereNotIn('status', ['cancelled', 'postponed'])
            ->get()
            ->map(fn (ClassSession $session) => [
                'type' => 'class_session',
                'id' => $session->id,
                'class_session_id' => $session->id,
                'room_id' => $session->room_id,
                'date' => $session->session_date->format('Y-m-d'),
                'title' => ($session->courseOffering?->unit?->code ?? 'Class').' - '.($session->courseOffering?->unit?->name ?? $session->session_title),
                'start_time' => $this->formatTime($session->start_time),
                'end_time' => $this->formatTime($session->end_time),
                'status' => $session->status,
                'instructor' => $session->lecture?->name,
                'is_editable' => false,
            ]);

        // Exam-resit blocks are a separate occupancy source (exam_room_slots), not
        // class_sessions. Only `scheduled` slots occupy a room. Slots already mirrored
        // into the canonical booking ledger surface as room_booking events above, so
        // skip them here to avoid double-display.
        $mirroredBookingIds = $bookingEvents
            ->pluck('booking_id')
            ->filter()
            ->values()
            ->all();

        $examEvents = ExamRoomSlot::query()
            ->with(['sessions:id,exam_room_slot_id,unit_id', 'sessions.unit:id,code,name'])
            ->whereIn('room_id', $roomIds)
            ->whereBetween('exam_date', [$startDate, $endDate])
            ->where('status', ExamRoomSlot::STATUS_SCHEDULED)
            ->when(! empty($mirroredBookingIds), fn ($query) => $query->whereNotIn('room_booking_id', $mirroredBookingIds))
            ->get()
            ->map(fn (ExamRoomSlot $slot) => [
                'type' => 'exam_room_slot',
                'id' => $slot->id,
                'exam_room_slot_id' => $slot->id,
                'room_id' => $slot->room_id,
                'date' => $slot->exam_date->format('Y-m-d'),
                'title' => $this->examTitle($slot),
                'start_time' => $this->formatTime($slot->start_time),
                'end_time' => $this->formatTime($slot->end_time),
                'status' => $slot->status,
                'session_count' => $slot->sessions->count(),
                'is_editable' => false,
            ]);

        $eventsByRoomDate = $bookingEvents
            ->concat($classEvents)
            ->concat($examEvents)
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
                'busy_events' => $bookingEvents->count() + $classEvents->count() + $examEvents->count(),
            ],
        ];
    }

    /**
     * Build the exam block label from its unit-scoped sessions. A slot may group
     * several small retake exams, so distinct unit codes are joined.
     */
    private function examTitle(ExamRoomSlot $slot): string
    {
        $unitCodes = $slot->sessions
            ->map(fn (ExamResitSession $session) => $session->unit?->code)
            ->filter()
            ->unique()
            ->values();

        return $unitCodes->isNotEmpty()
            ? 'Thi lại: '.$unitCodes->implode(', ')
            : 'Thi lại';
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
