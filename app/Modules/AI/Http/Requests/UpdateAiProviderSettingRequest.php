<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use App\Modules\AI\Models\AiProviderSetting;
use App\Modules\AI\Support\AiProviderCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAiProviderSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage_ai_provider_settings');
    }

    public function rules(): array
    {
        $catalog = app(AiProviderCatalog::class);

        return [
            'provider' => ['required', 'string', Rule::in($catalog->providerIds())],
            'default_model' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:4000'],
            'enabled' => ['required', 'boolean'],
            'daily_limit_cents' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'monthly_limit_cents' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $catalog = app(AiProviderCatalog::class);
                $provider = (string) $this->input('provider');
                $model = (string) $this->input('default_model');

                if ($provider !== '' && $model !== '' && ! $catalog->supportsModel($provider, $model)) {
                    $validator->errors()->add('default_model', 'The selected model is not available for this provider.');
                }

                $dailyLimit = $this->input('daily_limit_cents');
                $monthlyLimit = $this->input('monthly_limit_cents');

                if (is_numeric($dailyLimit) && is_numeric($monthlyLimit) && (int) $monthlyLimit < (int) $dailyLimit) {
                    $validator->errors()->add('monthly_limit_cents', 'The monthly limit must be greater than or equal to the daily limit.');
                }

                if ($this->boolean('enabled') && ! $this->hasUsableApiKey()) {
                    $validator->errors()->add('api_key', 'An API key is required before enabling the provider.');
                }
            },
        ];
    }

    public function payload(): array
    {
        $validated = $this->validated();

        return [
            ...$validated,
            'enabled' => (bool) $validated['enabled'],
            'daily_limit_cents' => isset($validated['daily_limit_cents']) ? (int) $validated['daily_limit_cents'] : null,
            'monthly_limit_cents' => isset($validated['monthly_limit_cents']) ? (int) $validated['monthly_limit_cents'] : null,
        ];
    }

    private function hasUsableApiKey(): bool
    {
        if (filled($this->input('api_key'))) {
            return true;
        }

        $setting = AiProviderSetting::query()
            ->where('user_id', $this->user()?->id)
            ->first();

        return (bool) $setting?->hasApiKey();
    }
}
