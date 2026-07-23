<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\SystemConfiguration;

use Illuminate\Foundation\Http\FormRequest;

final class UploadSystemConfigurationFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_system_config') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,gif', 'max:2048'],
            'config_key' => ['required', 'string', 'in:logo_full,logo_text'],
        ];
    }
}
