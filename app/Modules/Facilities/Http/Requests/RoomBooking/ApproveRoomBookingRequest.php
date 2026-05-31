<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\RoomBooking;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRoomBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve_room_booking') ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
