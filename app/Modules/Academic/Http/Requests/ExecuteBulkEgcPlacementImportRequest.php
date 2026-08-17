<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteBulkEgcPlacementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'preview_token' => ['required', 'string'],
        ];
    }
}
