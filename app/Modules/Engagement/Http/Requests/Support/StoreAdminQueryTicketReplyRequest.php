<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

final class StoreAdminQueryTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contextConfig = config('uploads.contexts.form_attachment', config('uploads.defaults'));
        $maxSize = (int) ($contextConfig['max_size'] ?? config('uploads.defaults.max_size', 10240));
        $allowedExtensions = array_values(array_unique($contextConfig['allowed_extensions'] ?? []));
        $allowedMimeTypes = array_values(array_unique($contextConfig['allowed_types'] ?? []));

        if (empty($allowedExtensions)) {
            $allowedExtensions = array_values(array_unique(config('uploads.defaults.allowed_extensions', [])));
        }

        if (empty($allowedMimeTypes)) {
            $allowedMimeTypes = array_values(array_unique(config('uploads.defaults.allowed_types', [])));
        }

        $fileRule = ! empty($allowedExtensions)
            ? File::types($allowedExtensions)->max($maxSize)
            : File::default()->max($maxSize);

        $attachmentRules = ['nullable', 'file', $fileRule];

        if (! empty($allowedExtensions)) {
            $attachmentRules[] = 'mimes:'.implode(',', $allowedExtensions);
        }

        if (! empty($allowedMimeTypes)) {
            $attachmentRules[] = 'mimetypes:'.implode(',', $allowedMimeTypes);
        }

        return [
            'message' => ['required', 'string'],
            'is_official_answer' => ['sometimes', 'boolean'],
            'set_pending' => ['sometimes', 'boolean'],
            'attachment' => $attachmentRules,
        ];
    }
}
