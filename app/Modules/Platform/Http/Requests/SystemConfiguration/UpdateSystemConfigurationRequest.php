<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\SystemConfiguration;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSystemConfigurationRequest extends FormRequest
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
            'app_name' => ['sometimes', 'string', 'max:255'],
            'logo_full' => ['sometimes', 'string', 'max:500'],
            'logo_text' => ['sometimes', 'string', 'max:500'],
            'copyright_text' => ['sometimes', 'string', 'max:500'],
            'country' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
