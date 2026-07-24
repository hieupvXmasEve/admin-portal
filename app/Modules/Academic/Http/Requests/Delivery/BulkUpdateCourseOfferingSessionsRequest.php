<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCourseOfferingSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'session_ids' => ['required', 'array', 'min:1'],
            'session_ids.*' => ['required', 'integer', 'exists:class_sessions,id'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && $this->input('start_time') !== null && $value <= $this->input('start_time')) {
                    $fail('The end time must be after start time.');
                }
            }],
            'lecture_id' => ['nullable', 'integer', 'exists:lectures,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ];
    }
}
