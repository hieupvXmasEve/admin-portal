<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Requests\SystemConfiguration;

use App\Modules\Platform\Support\SystemConfigurationDefinition;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateSystemConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasSystemRole('super_admin') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return app(SystemConfigurationDefinition::class)->validationRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowedKeys = app(SystemConfigurationDefinition::class)->keys();
            $unknownKeys = array_diff(array_keys($this->all()), $allowedKeys);

            if ($unknownKeys !== []) {
                $validator->errors()->add(
                    'configuration',
                    'Unsupported system configuration keys: '.implode(', ', $unknownKeys).'.',
                );
            }
        });
    }
}
