<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use Illuminate\Foundation\Http\FormRequest;

class PreviewLectureImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import_lecturer') ?? false;
    }

    public function rules(): array
    {
        return [
            'file_path' => ['required', 'string'],
            'preview_rows' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
