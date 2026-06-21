<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiProviderCatalog
{
    public function defaultProvider(): string
    {
        return (string) config('ai_provider_settings.default_provider', 'openai');
    }

    public function defaultModel(): string
    {
        return (string) config('ai_provider_settings.default_model', 'gpt-4o-mini');
    }

    public function providers(): array
    {
        return collect(config('ai_provider_settings.providers', []))
            ->map(fn (array $provider): array => [
                'id' => (string) $provider['id'],
                'label' => (string) $provider['label'],
            ])
            ->values()
            ->all();
    }

    public function modelsByProvider(): array
    {
        return collect(config('ai_provider_settings.providers', []))
            ->mapWithKeys(fn (array $provider, string $id): array => [
                $id => $this->modelsForProvider($id),
            ])
            ->all();
    }

    public function providerIds(): array
    {
        return array_keys(config('ai_provider_settings.providers', []));
    }

    public function modelIdsForProvider(?string $provider): array
    {
        if (! $provider) {
            return [];
        }

        return collect($this->modelsForProvider($provider))
            ->pluck('id')
            ->map(fn (mixed $model): string => (string) $model)
            ->all();
    }

    public function supportsProvider(string $provider): bool
    {
        return in_array($provider, $this->providerIds(), true);
    }

    public function supportsModel(string $provider, string $model): bool
    {
        return in_array($model, $this->modelIdsForProvider($provider), true);
    }

    public function provider(string $provider): ?array
    {
        $configuredProvider = config("ai_provider_settings.providers.{$provider}");

        return is_array($configuredProvider) ? $configuredProvider : null;
    }

    public function timeoutSeconds(): int
    {
        return (int) config('ai_provider_settings.timeout_seconds', 10);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function modelsForProvider(string $provider): array
    {
        $configuredProvider = $this->provider($provider);

        if (! $configuredProvider) {
            return [];
        }

        $fallbackModels = $this->configuredModels($configuredProvider);

        if (($configuredProvider['models_source'] ?? null) !== 'openrouter_api') {
            return $fallbackModels;
        }

        return $this->openRouterModels($configuredProvider, $fallbackModels);
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array<int, array{id: string, label: string}>
     */
    private function configuredModels(array $provider): array
    {
        return collect($provider['models'] ?? [])
            ->map(fn (array $model): array => [
                'id' => (string) $model['id'],
                'label' => (string) $model['label'],
            ])
            ->filter(fn (array $model): bool => $model['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<int, array{id: string, label: string}>  $fallbackModels
     * @return array<int, array{id: string, label: string}>
     */
    private function openRouterModels(array $provider, array $fallbackModels): array
    {
        $url = (string) ($provider['models_url'] ?? '');

        if ($url === '') {
            return $fallbackModels;
        }

        $cacheKey = 'ai_provider_settings.openrouter.models.'.sha1($url);
        $ttlSeconds = max(0, (int) ($provider['models_cache_ttl_seconds'] ?? 21600));
        $fetchModels = fn (): array => $this->fetchOpenRouterModels($url, $fallbackModels);

        $models = $ttlSeconds > 0
            ? Cache::remember($cacheKey, $ttlSeconds, $fetchModels)
            : $fetchModels();

        return is_array($models) ? $models : $fallbackModels;
    }

    /**
     * @param  array<int, array{id: string, label: string}>  $fallbackModels
     * @return array<int, array{id: string, label: string}>
     */
    private function fetchOpenRouterModels(string $url, array $fallbackModels): array
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->acceptJson()
                ->get($url, ['output_modalities' => 'text']);
        } catch (ConnectionException) {
            return $fallbackModels;
        } catch (Throwable) {
            return $fallbackModels;
        }

        if (! $response->successful()) {
            return $fallbackModels;
        }

        $models = $response->json('data');

        if (! is_array($models)) {
            return $fallbackModels;
        }

        $remoteModels = collect($models)
            ->filter(fn (mixed $model): bool => is_array($model))
            ->filter(fn (array $model): bool => $this->hasTextOutput($model))
            ->map(fn (array $model): array => [
                'id' => (string) ($model['id'] ?? ''),
                'label' => (string) ($model['name'] ?? $model['id'] ?? ''),
            ])
            ->filter(fn (array $model): bool => $model['id'] !== '')
            ->values()
            ->all();

        return collect($fallbackModels)
            ->merge($remoteModels)
            ->unique('id')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $model
     */
    private function hasTextOutput(array $model): bool
    {
        $modalities = data_get($model, 'architecture.output_modalities');

        if (! is_array($modalities)) {
            return true;
        }

        return in_array('text', $modalities, true);
    }
}
