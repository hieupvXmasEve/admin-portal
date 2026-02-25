<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewStudentActionsImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'shared_upload_record_id' => [
                'nullable',
                'integer',
                Rule::exists('upload_records', 'id')
                    ->where(fn ($q) => $q->where('user_id', $this->user()->id)->where('context', 'action_attachment')),
            ],
        ];
    }
}
