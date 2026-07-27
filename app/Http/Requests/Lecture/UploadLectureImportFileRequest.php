<?php

declare(strict_types=1);

namespace App\Http\Requests\Lecture;

use Illuminate\Foundation\Http\FormRequest;

class UploadLectureImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import_lecturer') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
            'duplicate_handling' => ['nullable', 'in:skip,update,error'],
        ];
    }
}
