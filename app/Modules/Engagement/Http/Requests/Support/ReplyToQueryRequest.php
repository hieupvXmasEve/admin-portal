<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class ReplyToQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contextConfig = config('uploads.contexts.form_attachment', config('uploads.defaults'));
        $maxSize = (int) ($contextConfig['max_size'] ?? 10240);
        $allowedExtensions = $contextConfig['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];

        return [
            'message' => ['required', 'string', 'min:2'],
            'is_official_answer' => ['sometimes', 'boolean'],
            'set_pending' => ['sometimes', 'boolean'],
            'attachment' => ['nullable', 'file', File::types($allowedExtensions)->max($maxSize)],
        ];
    }
}
