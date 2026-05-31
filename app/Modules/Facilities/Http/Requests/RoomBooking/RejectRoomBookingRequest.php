<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\RoomBooking;

use Illuminate\Foundation\Http\FormRequest;

class RejectRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve_room_booking') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for rejecting the booking.',
        ];
    }
}
