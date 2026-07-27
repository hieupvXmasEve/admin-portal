<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission check should be handled by Policy/Gate in Controller or Route
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'target_course_offering_id' => ['required', 'integer', 'exists:course_offerings,id'],
            'force_move' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_course_offering_id.required' => 'Please select a target section.',
            'target_course_offering_id.exists' => 'The selected section does not exist.',
        ];
    }
}
