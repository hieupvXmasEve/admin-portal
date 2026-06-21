<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Models\User;
use App\Modules\AI\Models\AiProviderSetting;
use App\Support\BusinessActionLogger;
use Illuminate\Support\Facades\DB;

class UpdateAiProviderSettingAction
{
    public static function run(User $owner, User $actor, array $data): AiProviderSetting
    {
        return DB::transaction(function () use ($owner, $actor, $data): AiProviderSetting {
            $setting = AiProviderSetting::query()->firstOrNew(['user_id' => $owner->id]);
            $keyChanged = array_key_exists('api_key', $data) && filled($data['api_key']);

            $setting->provider = (string) $data['provider'];
            $setting->default_model = (string) $data['default_model'];
            $setting->enabled = (bool) $data['enabled'];
            $setting->daily_limit_cents = $data['daily_limit_cents'] ?? null;
            $setting->monthly_limit_cents = $data['monthly_limit_cents'] ?? null;
            $setting->updated_by_user_id = $actor->id;

            if (! $setting->exists) {
                $setting->created_by_user_id = $actor->id;
            }

            if ($keyChanged) {
                $setting->encrypted_api_key = (string) $data['api_key'];
                $setting->last_test_status = null;
                $setting->last_test_error_code = null;
                $setting->tested_at = null;
            }

            $setting->save();

            BusinessActionLogger::for('ai_provider_settings.update', 'AI provider settings updated')
                ->on($setting)
                ->by($actor)
                ->withProperties([
                    'provider' => $setting->provider,
                    'model' => $setting->default_model,
                    'enabled' => $setting->enabled,
                    'daily_limit_cents' => $setting->daily_limit_cents,
                    'monthly_limit_cents' => $setting->monthly_limit_cents,
                    'key_changed' => $keyChanged,
                ])
                ->log();

            return $setting;
        });
    }
}
