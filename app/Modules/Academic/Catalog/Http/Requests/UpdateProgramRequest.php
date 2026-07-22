<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Program $program */
        $program = $this->route('program');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'name')->ignore($program->id),
            ],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('programs', 'code')->ignore($program->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Program name is required.',
            'name.unique' => 'A program with this name already exists.',
            'code.required' => 'Program code is required.',
            'code.unique' => 'A program with this code already exists.',
            'description.max' => 'Description must be less than 255 characters.',
        ];
    }
}
