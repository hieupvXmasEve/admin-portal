<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Student;

use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GradeFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'completion_status' => ['nullable', 'string', 'in:completed,in_progress,failed,withdrawn'],
            'grade' => ['nullable', 'string', 'in:HD,D,C,P,N,F'],
            'unit_code' => ['nullable', 'string', 'max:20'],
            'min_gpa' => ['nullable', 'numeric', 'min:0', 'max:4'],
            'max_gpa' => ['nullable', 'numeric', 'min:0', 'max:4', 'gte:min_gpa'],
            'sort_by' => ['nullable', 'string', 'in:semester,grade,unit_code,completion_date'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'semester_id.exists' => 'The selected semester does not exist',
            'completion_status.in' => 'Completion status must be one of: completed, in_progress, failed, withdrawn',
            'grade.in' => 'Grade must be one of: HD, D, C, P, N, F',
            'unit_code.max' => 'Unit code cannot exceed 20 characters',
            'min_gpa.min' => 'Minimum GPA cannot be less than 0',
            'min_gpa.max' => 'Minimum GPA cannot be greater than 4',
            'max_gpa.min' => 'Maximum GPA cannot be less than 0',
            'max_gpa.max' => 'Maximum GPA cannot be greater than 4',
            'max_gpa.gte' => 'Maximum GPA must be greater than or equal to minimum GPA',
            'sort_by.in' => 'Sort by must be one of: semester, grade, unit_code, completion_date',
            'sort_direction.in' => 'Sort direction must be either asc or desc',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Invalid grade filter parameters'
            )
        );
    }
}
