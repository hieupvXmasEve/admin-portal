<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\RoomBooking;

use App\Models\RoomBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_room_booking') ?? false;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['sometimes', 'integer', 'exists:rooms,id'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'booking_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i', 'after:start_time'],
            'booking_type' => ['sometimes', 'string', Rule::in(RoomBooking::getBookingTypes())],
            'priority' => ['nullable', 'string', Rule::in(RoomBooking::getPriorities())],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'required_equipment' => ['nullable', 'array'],
            'required_equipment.*' => ['string'],
            'setup_requirements' => ['nullable', 'array'],
            'setup_requirements.*' => ['string'],
            'special_requirements' => ['nullable', 'string', 'max:2000'],
            'send_reminders' => ['nullable', 'boolean'],
        ];
    }
}
