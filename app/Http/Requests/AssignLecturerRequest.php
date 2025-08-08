<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignLecturerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('assign_lecturer');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'course_offering_id' => [
                'required',
                'integer',
                'exists:course_offerings,id',
            ],
            'lecturer_id' => [
                'required',
                'integer',
                'exists:lectures,id',
            ],
            'force_assignment' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'course_offering_id.required' => 'Course offering is required.',
            'course_offering_id.exists' => 'The selected course offering does not exist.',
            'lecturer_id.required' => 'Lecturer is required.',
            'lecturer_id.exists' => 'The selected lecturer does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'course_offering_id' => 'course offering',
            'lecturer_id' => 'lecturer',
            'force_assignment' => 'force assignment',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation can be added here
            // For example, checking if lecturer is available for assignment
            if ($this->has('lecturer_id') && $this->has('course_offering_id')) {
                $lecturer = \App\Models\Lecture::find($this->lecturer_id);
                $courseOffering = \App\Models\CourseOffering::find($this->course_offering_id);

                if ($lecturer && $courseOffering) {
                    // Check if lecturer is available for assignment
                    if (! $lecturer->isAvailableForAssignment()) {
                        $validator->errors()->add('lecturer_id', 'The selected lecturer is not available for assignment.');
                    }

                    // Check for schedule conflicts unless force assignment is enabled
                    if (! $this->boolean('force_assignment') && $lecturer->hasScheduleConflictWith($courseOffering)) {
                        $validator->errors()->add('lecturer_id', 'The selected lecturer has schedule conflicts with this course offering.');
                    }
                }
            }
        });
    }
}
