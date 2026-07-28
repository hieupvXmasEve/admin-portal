<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Requests\Placement;

use Illuminate\Foundation\Http\FormRequest;

class TransitionToIntakeCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'ielts_certificate_id' => ['required', 'integer', 'exists:ielts_certificates,id'],
            'allow_missing_documents' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required.',
            'student_id.exists' => 'Student not found.',
            'semester_id.required' => 'Semester is required.',
            'semester_id.exists' => 'Semester not found.',
            'ielts_certificate_id.required' => 'IELTS certificate is required for transition.',
            'ielts_certificate_id.exists' => 'IELTS certificate not found.',
        ];
    }

    public function validatedWithUser(): array
    {
        return array_merge($this->validated(), [
            'created_by_user_id' => $this->user()->id,
        ]);
    }
}
