<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamRoomSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'room_booking_id' => ['nullable', 'integer', 'exists:room_bookings,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
