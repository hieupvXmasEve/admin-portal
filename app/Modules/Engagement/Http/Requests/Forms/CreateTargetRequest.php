<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateTargetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'form_version_id' => [
                'nullable',
                Rule::exists('form_versions', 'id')
                    ->where('form_id', $this->route('form')?->id),
            ],
            'campus_id' => ['nullable', 'exists:campuses,id'],
            'scope_type' => [
                'required',
                'string',
                Rule::in(['section', 'class_session', 'course', 'global', 'department']),
            ],
            'scope_id' => ['nullable', 'string'], // Departments usually use string codes or ints, safer to allow string
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'submission_limit_per_user' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'is_mandatory' => ['nullable', 'boolean'],
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
