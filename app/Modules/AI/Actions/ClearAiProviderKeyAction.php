<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Models\User;
use App\Modules\AI\Models\AiProviderSetting;
use App\Support\BusinessActionLogger;
use Illuminate\Support\Facades\DB;

class ClearAiProviderKeyAction
{
    public static function run(User $owner, User $actor): AiProviderSetting
    {
        return DB::transaction(function () use ($owner, $actor): AiProviderSetting {
            $setting = AiProviderSetting::query()->firstOrCreate(
                ['user_id' => $owner->id],
                [
                    'provider' => (string) config('ai_provider_settings.default_provider', 'openai'),
                    'default_model' => (string) config('ai_provider_settings.default_model', 'gpt-4o-mini'),
                    'enabled' => false,
                    'created_by_user_id' => $actor->id,
                ]
            );

            $setting->forceFill([
                'encrypted_api_key' => null,
                'enabled' => false,
                'last_test_status' => null,
                'last_test_error_code' => null,
                'tested_at' => null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            BusinessActionLogger::for('ai_provider_settings.key.clear', 'AI provider key cleared')
                ->on($setting)
                ->by($actor)
                ->withProperties([
                    'provider' => $setting->provider,
                    'model' => $setting->default_model,
                    'key_cleared' => true,
                ])
                ->log();

            return $setting;
        });
    }
}
