<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class GetRegistrationsRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year' => 'nullable|string|max:20',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'status' => 'nullable|string|in:all,pending,registered,confirmed,dropped,withdrawn,completed,waitlisted,failed',
            'is_retake' => 'nullable|string|in:all,true,false',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|string|in:course_name,course_code,semester,registration_status,final_grade,pass_fail_status,registration_date',
            'direction' => 'nullable|string|in:asc,desc',
        ];
    }
}
