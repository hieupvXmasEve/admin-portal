<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\SystemConfiguration;

use Illuminate\Foundation\Http\FormRequest;

final class UploadSystemConfigurationFileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('slot') && $this->has('config_key')) {
            $this->merge(['slot' => $this->input('config_key')]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->hasSystemRole('super_admin') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'slot' => ['required', 'string', 'in:logo_full,logo_text,favicon,apple_touch_icon'],
        ];
    }
}
