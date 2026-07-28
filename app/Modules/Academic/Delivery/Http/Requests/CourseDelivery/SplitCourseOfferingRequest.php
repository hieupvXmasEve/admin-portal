<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\CourseDelivery;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class SplitCourseOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit_course_offering');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number_of_sections' => ['required', 'integer', 'min:2', 'max:10'],
            'assignment_mode' => ['required', 'in:equal,custom'],
            'sections' => ['required', 'array'],
            'sections.*.section_code' => ['required', 'string', 'max:10'],
            'sections.*.max_capacity' => ['required', 'integer', 'min:1'],
            'sections.*.lecture_id' => ['nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && $value !== 'none' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $fail('The selected lecture must be an integer.');
                }
            }],
            'sections.*.location' => ['nullable', 'string', 'max:255'],
            'sections.*.student_ids' => ['required', 'array'],
            'sections.*.student_ids.*' => ['exists:students,id'],
        ];
    }
}
