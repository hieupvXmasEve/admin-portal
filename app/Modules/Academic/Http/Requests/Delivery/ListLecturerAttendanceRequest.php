<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListLecturerAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'course_offering_id' => ['nullable', 'integer', 'exists:course_offerings,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled', 'in_progress'])],
            'attendance_status' => ['nullable', Rule::in(['marked', 'unmarked', 'all'])],
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
