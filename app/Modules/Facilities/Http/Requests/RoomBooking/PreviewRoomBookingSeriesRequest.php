<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\RoomBooking;

use Illuminate\Foundation\Http\FormRequest;

class PreviewRoomBookingSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create_room_booking') ?? false;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'occurrences' => ['required', 'array', 'min:1', 'max:366'],
            'occurrences.*.booking_date' => ['required', 'date', 'after_or_equal:today'],
            'occurrences.*.start_time' => ['required', 'date_format:H:i'],
            'occurrences.*.end_time' => ['required', 'date_format:H:i'],
            'exclude_booking_id' => ['nullable', 'integer', 'exists:room_bookings,id'],
        ];
    }
}
