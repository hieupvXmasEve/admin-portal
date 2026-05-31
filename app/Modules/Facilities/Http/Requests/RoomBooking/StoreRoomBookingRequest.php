<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\RoomBooking;

use App\Models\RoomBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create_room_booking') ?? false;
    }

    public function rules(): array
    {
        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'schedule_mode' => ['nullable', 'string', Rule::in(['single', 'range'])],
            'booking_date' => ['nullable', 'date', 'after_or_equal:today'],
            'date_from' => ['nullable', 'date', 'after_or_equal:today'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'all_days' => ['nullable', 'boolean'],
            'selected_weekdays' => ['nullable', 'array'],
            'selected_weekdays.*' => ['string', Rule::in($weekdays)],
            'occurrences' => ['nullable', 'array', 'max:366'],
            'occurrences.*.booking_date' => ['required_with:occurrences', 'date', 'after_or_equal:today'],
            'occurrences.*.start_time' => ['required_with:occurrences', 'date_format:H:i'],
            'occurrences.*.end_time' => ['required_with:occurrences', 'date_format:H:i'],
            'start_time' => ['required_without:occurrences', 'date_format:H:i'],
            'end_time' => ['required_without:occurrences', 'date_format:H:i', 'after:start_time'],
            'booking_type' => ['required', 'string', Rule::in(RoomBooking::getBookingTypes())],
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

    public function attributes(): array
    {
        return [
            'room_id' => 'room',
            'booking_date' => 'booking date',
            'date_from' => 'start date',
            'date_to' => 'end date',
            'occurrences' => 'booking occurrences',
            'occurrences.*.booking_date' => 'occurrence date',
            'occurrences.*.start_time' => 'occurrence start time',
            'occurrences.*.end_time' => 'occurrence end time',
            'start_time' => 'start time',
            'end_time' => 'end time',
            'booking_type' => 'booking type',
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required' => 'Please select a room.',
            'room_id.exists' => 'The selected room does not exist.',
            'booking_date.after_or_equal' => 'Booking date must be today or in the future.',
            'date_from.after_or_equal' => 'Start date must be today or in the future.',
            'date_to.after_or_equal' => 'End date must be the same as or after the start date.',
            'occurrences.max' => 'A booking set can include at most 366 occurrences.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
            'booking_type.in' => 'The selected booking type is invalid.',
            'priority.in' => 'The selected priority is invalid.',
        ];
    }
}
