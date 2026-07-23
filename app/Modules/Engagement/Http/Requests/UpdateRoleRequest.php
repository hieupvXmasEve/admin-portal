<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
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
            'role' => [
                'required',
                'string',
                'in:president,vice_president,secretary,treasurer,member',
            ],
            'change_reason' => ['nullable', 'string', 'max:1000'],
            'responsibilities' => ['nullable', 'array'],
            'responsibilities.*' => ['string', 'max:255'],
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
            'role.required' => 'The role field is required.',
            'role.string' => 'The role must be text.',
            'role.in' => 'The role must be one of: president, vice_president, secretary, treasurer, member.',
            'change_reason.string' => 'The change reason must be text.',
            'change_reason.max' => 'The change reason cannot exceed 1000 characters.',
            'responsibilities.array' => 'Responsibilities must be a list.',
            'responsibilities.*.string' => 'Each responsibility must be text.',
            'responsibilities.*.max' => 'Each responsibility cannot exceed 255 characters.',
        ];
    }
}
