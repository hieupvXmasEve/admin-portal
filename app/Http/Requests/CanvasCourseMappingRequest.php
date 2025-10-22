<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CanvasCourseMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mapping_id' => ['required', 'exists:canvas_course_mappings,id'],
            'course_offering_id' => ['required', 'exists:course_offerings,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'mapping_id.required' => 'Canvas course mapping is required',
            'mapping_id.exists' => 'Selected Canvas course mapping does not exist',
            'course_offering_id.required' => 'Course offering is required',
            'course_offering_id.exists' => 'Selected course offering does not exist',
        ];
    }
}
