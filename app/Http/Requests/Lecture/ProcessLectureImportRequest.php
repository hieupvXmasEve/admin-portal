<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use Illuminate\Foundation\Http\FormRequest;

class ProcessLectureImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import_lecturer') ?? false;
    }

    public function rules(): array
    {
        return [
            'file_path' => ['required', 'string'],
            'duplicate_handling' => ['nullable', 'in:skip,update,error'],
        ];
    }
}
