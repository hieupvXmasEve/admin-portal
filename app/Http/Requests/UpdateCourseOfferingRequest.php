<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CourseOffering;
use App\Rules\CourseOffering\AssignableSyllabusTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseOfferingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit_course');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = CourseOffering::validationRules();
        $courseOffering = $this->route('courseOffering');
        $rules['syllabus_template_id'][] = new AssignableSyllabusTemplate(
            $courseOffering instanceof CourseOffering ? $courseOffering : null
        );

        return $rules;
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return CourseOffering::validationMessages();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'grading_type' => $this->input('grading_type', 'grade'),
        ]);
    }
}
