<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTargetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'form_version_id' => ['nullable', 'exists:form_versions,id'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'scope_type' => [
                'required',
                'string',
                Rule::in(['section', 'class_session', 'course', 'global'])
            ],
            'scope_id' => ['nullable', 'integer'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'submission_limit_per_user' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'scope_type.required' => 'Scope type is required',
            'scope_type.in' => 'Scope type must be one of: section, class_session, course, global',
            'start_at.required' => 'Start date is required',
            'end_at.after' => 'End date must be after start date',
            'submission_limit_per_user.required' => 'Submission limit is required',
            'submission_limit_per_user.min' => 'Submission limit must be at least 1',
        ];
    }
}
