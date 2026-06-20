<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Support;

use App\Models\ClassSession;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Services\SystemConfigService;
use Carbon\Carbon;

class RoomBookingSlotValidator
{
    private const DEFAULT_BOOKING_START_TIME = '07:00';

    private const DEFAULT_BOOKING_END_TIME = '20:00';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly ExamSlotBookingConflictChecker $examSlotConflictChecker
    ) {}

    public function validateRoomAndSlot(Room $room, string $date, string $startTime, string $endTime, ?int $campusId = null): void
    {
        if ($campusId !== null && (int) $room->campus_id !== $campusId) {
            throw new \InvalidArgumentException('The selected room does not belong to the current campus.');
        }

        $this->validateRoomIsBookable($room);
        $this->validateTimeSlot($room, $date, $startTime, $endTime);
    }

    public function conflictsFor(int $roomId, string $date, string $startTime, string $endTime, ?int $excludeBookingId = null): array
    {
        $conflicts = [];

        $bookingConflicts = RoomBooking::query()
            ->forRoom($roomId)
            ->forDate($date)
            ->activeBookings()
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->when($excludeBookingId, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->get();

        foreach ($bookingConflicts as $booking) {
            $conflicts[] = [
                'type' => 'room_booking',
                'id' => $booking->id,
                'title' => $booking->title,
                'booking_date' => $booking->booking_date->format('Y-m-d'),
                'start_time' => $this->formatTime($booking->start_time),
                'end_time' => $this->formatTime($booking->end_time),
                'status' => $booking->status,
                'booking_id' => $booking->id,
            ];
        }

        $sessionConflicts = ClassSession::query()
            ->where('room_id', $roomId)
            ->whereDate('session_date', $date)
            ->whereNotIn('status', ['cancelled', 'postponed'])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereRaw('TIME(start_time) < ?', [$endTime])
                    ->whereRaw('TIME(end_time) > ?', [$startTime]);
            })
            ->with(['courseOffering.unit'])
            ->get();

        foreach ($sessionConflicts as $session) {
            $conflicts[] = [
                'type' => 'class_session',
                'id' => $session->id,
                'title' => ($session->courseOffering?->unit?->code ?? 'Class').' - '.($session->courseOffering?->unit?->name ?? $session->session_title),
                'booking_date' => $session->session_date->format('Y-m-d'),
                'start_time' => $this->formatTime($session->start_time),
                'end_time' => $this->formatTime($session->end_time),
                'status' => $session->status,
                'class_session_id' => $session->id,
                'is_editable' => false,
            ];
        }

        // Scheduled exam-resit blocks are a third occupancy source (see S-003).
        foreach ($this->examSlotConflictChecker->conflictsFor($roomId, $date, $startTime, $endTime, $excludeBookingId) as $examConflict) {
            $conflicts[] = $examConflict;
        }

        return $conflicts;
    }

    public function effectiveWindow(Room $room, string $requestedStart, string $requestedEnd): array
    {
        $systemStart = $this->systemConfig('system_booking_start_time', self::DEFAULT_BOOKING_START_TIME);
        $systemEnd = $this->systemConfig('system_booking_end_time', self::DEFAULT_BOOKING_END_TIME);

        $roomStart = $room->available_from ? $this->formatTime($room->available_from) : $systemStart;
        $roomEnd = $room->available_until ? $this->formatTime($room->available_until) : $systemEnd;

        return [
            'start_time' => max($requestedStart, $systemStart, $roomStart),
            'end_time' => min($requestedEnd, $systemEnd, $roomEnd),
        ];
    }

    private function validateRoomIsBookable(Room $room): void
    {
        if (! $room->is_bookable) {
            throw new \InvalidArgumentException('This room is not available for booking.');
        }

        if (! in_array($room->status, [Room::STATUS_AVAILABLE], true)) {
            throw new \InvalidArgumentException('This room is currently not available (status: '.$room->status.').');
        }
    }

    private function validateTimeSlot(Room $room, string $date, string $startTime, string $endTime): void
    {
        $bookingDate = Carbon::parse($date)->startOfDay();

        if ($bookingDate->lt(Carbon::today())) {
            throw new \InvalidArgumentException('Cannot book a room for a past date.');
        }

        if ($bookingDate->isToday()) {
            $startDateTime = Carbon::parse($date.' '.$startTime);
            if ($startDateTime->lt(Carbon::now())) {
                throw new \InvalidArgumentException('Start time must be in the future for today\'s booking.');
            }
        }

        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('Start time must be before end time.');
        }

        $systemStart = $this->systemConfig('system_booking_start_time', self::DEFAULT_BOOKING_START_TIME);
        $systemEnd = $this->systemConfig('system_booking_end_time', self::DEFAULT_BOOKING_END_TIME);

        $roomStart = $room->available_from ? $this->formatTime($room->available_from) : $systemStart;
        $roomEnd = $room->available_until ? $this->formatTime($room->available_until) : $systemEnd;

        $effectiveStart = max($systemStart, $roomStart);
        $effectiveEnd = min($systemEnd, $roomEnd);

        if ($startTime < $effectiveStart) {
            throw new \InvalidArgumentException("Booking cannot start before {$effectiveStart}.");
        }

        if ($endTime > $effectiveEnd) {
            throw new \InvalidArgumentException("Booking cannot end after {$effectiveEnd}.");
        }

        if ($room->blocked_days && in_array($bookingDate->format('l'), $room->blocked_days, true)) {
            throw new \InvalidArgumentException('This room is not available on '.$bookingDate->format('l').'.');
        }
    }

    private function systemConfig(string $key, mixed $default): mixed
    {
        return $this->systemConfigService->get($key, $default);
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
