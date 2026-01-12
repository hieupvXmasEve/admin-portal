<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Placement;

use Illuminate\Foundation\Http\FormRequest;

class RecordIeltsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'overall_score' => ['required', 'numeric', 'min:0', 'max:9'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'upload_record_id' => ['nullable', 'integer', 'exists:upload_records,id'],
            'missing_documents' => ['nullable', 'boolean'],
            'issue_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required.',
            'student_id.exists' => 'Student not found.',
            'overall_score.required' => 'IELTS overall score is required.',
            'overall_score.min' => 'IELTS score must be at least 0.',
            'overall_score.max' => 'IELTS score cannot exceed 9.',
            'semester_id.required' => 'Semester is required.',
            'semester_id.exists' => 'Semester not found.',
        ];
    }

    public function validatedWithUser(): array
    {
        return array_merge($this->validated(), [
            'created_by_user_id' => $this->user()->id,
        ]);
    }
}
