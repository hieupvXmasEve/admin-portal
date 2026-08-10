<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `password` is nullable on purpose: the UI is write-only for it (never
 * echoes the current value back), so a blank submit means "keep the current
 * password" — see {@see \App\Modules\Admissions\Support\Crm\CrmIntegrationSettings::save()}.
 */
final class SaveCrmIntegrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login_url' => ['required', 'url', 'max:255'],
            'data_url' => ['required', 'url', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'timeout' => ['required', 'integer', 'min:1', 'max:600'],
        ];
    }
}
