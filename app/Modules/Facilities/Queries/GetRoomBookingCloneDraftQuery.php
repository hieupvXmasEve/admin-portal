<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Queries;

use App\Models\RoomBooking;
use Carbon\Carbon;

class GetRoomBookingCloneDraftQuery
{
    public function handle(RoomBooking $booking): array
    {
        $booking->loadMissing(['room.building', 'room.campus']);

        $today = Carbon::today()->format('Y-m-d');

        return [
            'source_booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'title' => $booking->title,
            'description' => $booking->description,
            'booking_date' => $today,
            'date_from' => $today,
            'date_to' => $today,
            'start_time' => $this->formatTime($booking->start_time),
            'end_time' => $this->formatTime($booking->end_time),
            'booking_type' => $booking->booking_type,
            'priority' => $booking->priority,
            'contact_person' => $booking->contact_person,
            'contact_phone' => $booking->contact_phone,
            'contact_email' => $booking->contact_email,
            'special_requirements' => $booking->special_requirements,
            'room' => $booking->room,
        ];
    }

    private function formatTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
