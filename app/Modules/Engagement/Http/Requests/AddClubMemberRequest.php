<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddClubMemberRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'role' => ['required', 'string', 'in:member,vice_president,secretary,treasurer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Student is required.',
            'student_id.integer' => 'Student must be a valid selection.',
            'student_id.exists' => 'The selected student does not exist.',
            'role.required' => 'Role is required.',
            'role.string' => 'Role must be text.',
            'role.in' => 'Role must be one of: member, vice_president, secretary, treasurer.',
            'notes.string' => 'Notes must be text.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
