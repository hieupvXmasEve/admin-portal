<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Modules\Facilities\Models\RoomBooking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create_room_booking');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
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

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'room_id' => 'room',
            'title' => 'booking title',
            'booking_date' => 'booking date',
            'start_time' => 'start time',
            'end_time' => 'end time',
            'booking_type' => 'booking type',
            'contact_person' => 'contact person',
            'contact_phone' => 'contact phone',
            'contact_email' => 'contact email',
            'required_equipment' => 'required equipment',
            'setup_requirements' => 'setup requirements',
            'special_requirements' => 'special requirements',
            'send_reminders' => 'send reminders',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'room_id.required' => 'Please select a room.',
            'room_id.exists' => 'The selected room does not exist.',
            'booking_date.after_or_equal' => 'Booking date must be today or in the future.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
            'booking_type.in' => 'The selected booking type is invalid.',
            'priority.in' => 'The selected priority is invalid.',
        ];
    }
}

