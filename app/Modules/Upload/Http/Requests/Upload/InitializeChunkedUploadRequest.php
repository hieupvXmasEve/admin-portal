<?php

declare(strict_types=1);

namespace App\Modules\Upload\Http\Requests\Upload;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitializeChunkedUploadRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'file_size' => ['required', 'integer', 'min:1'],
            'context' => ['required', 'string', 'max:50', Rule::in($this->publicContexts())],
            'metadata' => ['sometimes', 'array'],
        ];
    }

    /** @return list<string> */
    private function publicContexts(): array
    {
        return array_keys(array_filter(
            config('uploads.contexts'),
            static fn (array $config): bool => ! ($config['internal'] ?? false),
        ));
    }
}
