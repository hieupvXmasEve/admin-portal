<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Modules\AI\Contracts\AiProviderTester;
use App\Modules\AI\Models\AiProviderSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenRouterAiProviderTester implements AiProviderTester
{
    public function test(AiProviderSetting $setting, array $resolvedProvider): AiProviderTestResult
    {
        $startedAt = microtime(true);

        if ($setting->provider !== 'openrouter') {
            return AiProviderTestResult::failed('unsupported_provider_test', $this->durationMs($startedAt));
        }

        $apiKey = (string) $setting->encrypted_api_key;

        if ($apiKey === '') {
            return AiProviderTestResult::failed('missing_api_key', $this->durationMs($startedAt));
        }

        $url = (string) ($resolvedProvider['chat_completions_url'] ?? '');

        if ($url === '') {
            return AiProviderTestResult::failed('provider_configuration_missing', $this->durationMs($startedAt));
        }

        try {
            $response = Http::timeout((int) $resolvedProvider['timeout_seconds'])
                ->connectTimeout(min(3, (int) $resolvedProvider['timeout_seconds']))
                ->acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->withHeaders([
                    'X-OpenRouter-Title' => 'Swinx',
                ])
                ->post($url, [
                    'model' => (string) $resolvedProvider['model'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a provider connectivity health check. Reply with exactly: ok',
                        ],
                        [
                            'role' => 'user',
                            'content' => 'Swinx AI provider health check. Reply ok.',
                        ],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 8,
                    'stream' => false,
                ]);
        } catch (ConnectionException) {
            return AiProviderTestResult::failed('provider_timeout', $this->durationMs($startedAt));
        }

        if ($response->successful() && $this->hasCompletionChoice($response)) {
            return AiProviderTestResult::success($this->durationMs($startedAt));
        }

        if ($response->successful()) {
            return AiProviderTestResult::failed('provider_response_invalid', $this->durationMs($startedAt));
        }

        return AiProviderTestResult::failed($this->errorCodeForStatus($response->status()), $this->durationMs($startedAt));
    }

    private function hasCompletionChoice(Response $response): bool
    {
        $choices = $response->json('choices');

        return is_array($choices) && count($choices) > 0;
    }

    private function errorCodeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'provider_request_invalid',
            401 => 'provider_authentication_failed',
            402 => 'provider_insufficient_credits',
            403 => 'provider_forbidden',
            404, 502 => 'provider_model_unavailable',
            408 => 'provider_timeout',
            429 => 'provider_rate_limited',
            503 => 'provider_temporarily_unavailable',
            default => $status >= 500 ? 'provider_temporarily_unavailable' : 'provider_response_invalid',
        };
    }

    private function durationMs(float $startedAt): int
    {
        return max(1, (int) round((microtime(true) - $startedAt) * 1000));
    }
}
