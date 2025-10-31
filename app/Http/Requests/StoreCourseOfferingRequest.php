<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CourseOffering;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseOfferingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create_course');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return CourseOffering::validationRules();
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
            'current_enrollment' => 0,
            'current_waitlist' => 0,
            'is_active' => true,
            'enrollment_status' => 'open',
            'grading_type' => $this->input('grading_type', 'grade'),
        ]);
    }
}
