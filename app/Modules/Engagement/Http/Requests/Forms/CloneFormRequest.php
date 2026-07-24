<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

final class CloneFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'new_code' => $this->input('new_code', $this->input('code')),
            'new_title' => $this->input('new_title', $this->input('title')),
        ]);
    }

    public function rules(): array
    {
        return [
            'new_code' => ['required', 'string', 'max:100', 'unique:forms,code'],
            'new_title' => ['required', 'string', 'max:255'],
        ];
    }
}
