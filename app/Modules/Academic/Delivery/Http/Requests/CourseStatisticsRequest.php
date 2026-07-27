<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CourseStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'exists:semesters,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort' => ['nullable', 'string', 'in:unit_code,unit_name,total_students,average_attendance,average_grade'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function messages(): array
    {
        return [
            'semester_id.exists' => 'The selected semester does not exist.',
            'search.max' => 'The search term cannot exceed 255 characters.',
            'per_page.integer' => 'Items per page must be a number.',
            'per_page.min' => 'Items per page must be at least 5.',
            'per_page.max' => 'Items per page cannot exceed 100.',
            'sort.in' => 'Invalid sort field.',
            'direction.in' => 'Sort direction must be asc or desc.',
        ];
    }
}
