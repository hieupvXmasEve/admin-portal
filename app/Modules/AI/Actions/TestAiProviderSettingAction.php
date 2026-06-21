<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Models\User;
use App\Modules\AI\Contracts\AiProviderTester;
use App\Modules\AI\Models\AiProviderSetting;
use App\Modules\AI\Support\AiProviderTestResult;
use App\Modules\AI\Support\LaravelAiProviderResolver;
use App\Support\BusinessActionLogger;
use Illuminate\Support\Facades\DB;

class TestAiProviderSettingAction
{
    public static function run(
        User $owner,
        User $actor,
        LaravelAiProviderResolver $resolver,
        AiProviderTester $tester,
    ): AiProviderSetting {
        return DB::transaction(function () use ($owner, $actor, $resolver, $tester): AiProviderSetting {
            $setting = AiProviderSetting::query()->firstOrCreate(
                ['user_id' => $owner->id],
                [
                    'provider' => (string) config('ai_provider_settings.default_provider', 'openai'),
                    'default_model' => (string) config('ai_provider_settings.default_model', 'gpt-4o-mini'),
                    'enabled' => false,
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ]
            );

            $resolvedProvider = $resolver->resolve($setting);
            $result = $setting->hasApiKey()
                ? $tester->test($setting, $resolvedProvider)
                : AiProviderTestResult::failed('missing_api_key', 1);

            $setting->forceFill([
                'tested_at' => now(),
                'last_test_status' => $result->status,
                'last_test_error_code' => $result->errorCode,
                'updated_by_user_id' => $actor->id,
            ])->save();

            BusinessActionLogger::for('ai_provider_settings.test', 'AI provider test attempted')
                ->on($setting)
                ->by($actor)
                ->withProperties([
                    'provider' => $setting->provider,
                    'model' => $setting->default_model,
                    'status' => $result->status,
                    'error_code' => $result->errorCode,
                    'duration_ms' => $result->durationMs,
                    'health_payload' => 'static_non_business_chat_completion',
                    'sdk_lab' => $resolvedProvider['sdk_lab'],
                    'sdk_installed' => $resolvedProvider['sdk_installed'],
                ])
                ->log();

            return $setting;
        });
    }
}
