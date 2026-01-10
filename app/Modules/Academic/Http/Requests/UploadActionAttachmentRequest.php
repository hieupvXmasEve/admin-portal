<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadActionAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attachment_ids' => ['required', 'array', 'min:1'],
            'attachment_ids.*' => ['integer', 'exists:upload_records,id'],
            'mark_documents_complete' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment_ids.required' => 'Please select at least one attachment.',
            'attachment_ids.min' => 'Please select at least one attachment.',
            'attachment_ids.*.exists' => 'One or more selected attachments do not exist.',
        ];
    }
}
