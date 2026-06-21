<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Modules\AI\Models\AiProviderSetting;
use InvalidArgumentException;

class LaravelAiProviderResolver
{
    public function __construct(private readonly AiProviderCatalog $catalog) {}

    public function resolve(AiProviderSetting $setting): array
    {
        $provider = $this->catalog->provider($setting->provider);

        if (! $provider || ! $this->catalog->supportsModel($setting->provider, $setting->default_model)) {
            throw new InvalidArgumentException('Unsupported AI provider or model.');
        }

        return [
            'provider' => $setting->provider,
            'model' => $setting->default_model,
            'sdk_lab' => (string) ($provider['sdk_lab'] ?? $setting->provider),
            'sdk_lab_enum' => 'Laravel\\Ai\\Enums\\Lab',
            'sdk_installed' => class_exists('Laravel\\Ai\\Enums\\Lab'),
            'timeout_seconds' => $this->catalog->timeoutSeconds(),
            'chat_completions_url' => $provider['chat_completions_url'] ?? null,
        ];
    }
}
