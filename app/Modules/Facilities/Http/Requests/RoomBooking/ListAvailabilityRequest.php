<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\RoomBooking;

use Illuminate\Foundation\Http\FormRequest;

class ListAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_room_booking') ?? false;
    }

    public function rules(): array
    {
        return [
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
        ];
    }
}
