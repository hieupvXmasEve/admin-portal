<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\CourseDelivery;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCourseOfferingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'module_id' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && $value !== '' && $value !== 'all' && ! ctype_digit((string) $value)) {
                        $fail('Invalid module selected.');
                    }
                },
            ],
            'enrollment_status' => ['nullable', 'string', Rule::in(['all', 'open', 'closed', 'waitlist_only', 'cancelled'])],
            'course_status' => ['nullable', 'string', Rule::in(['all', 'not_started', 'in_progress', 'completed', 'cancelled'])],
            'delivery_mode' => ['nullable', 'string', Rule::in(['all', 'in_person', 'online', 'hybrid', 'blended'])],
            'unit_level' => ['nullable', 'string'],
            'unit_type' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in([
                'unit_code',
                'unit_name',
                'unit_level',
                'unit_type',
                'delivery_mode',
                'course_status',
                'enrollment_status',
                'current_enrollment',
            ])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
