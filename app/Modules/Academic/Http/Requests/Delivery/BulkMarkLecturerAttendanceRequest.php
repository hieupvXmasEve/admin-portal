<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

final class BulkMarkLecturerAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sessions' => ['required', 'array', 'min:1', 'max:10'],
            'sessions.*.session_id' => ['required', 'integer'],
            'sessions.*.attendance_data' => ['required', 'array'],
            'sessions.*.attendance_data.*.student_id' => ['required', 'integer'],
            'sessions.*.attendance_data.*.status' => ['required', 'in:present,absent,late,excused'],
        ];
    }
}
