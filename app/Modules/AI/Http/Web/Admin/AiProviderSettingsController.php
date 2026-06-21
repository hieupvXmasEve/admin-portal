<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AI\Actions\ClearAiProviderKeyAction;
use App\Modules\AI\Actions\TestAiProviderSettingAction;
use App\Modules\AI\Actions\UpdateAiProviderSettingAction;
use App\Modules\AI\Contracts\AiProviderTester;
use App\Modules\AI\Http\Requests\ClearAiProviderKeyRequest;
use App\Modules\AI\Http\Requests\TestAiProviderSettingRequest;
use App\Modules\AI\Http\Requests\UpdateAiProviderSettingRequest;
use App\Modules\AI\Models\AiProviderSetting;
use App\Modules\AI\Support\AiProviderCatalog;
use App\Modules\AI\Support\LaravelAiProviderResolver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AiProviderSettingsController extends Controller
{
    public function __construct(private readonly AiProviderCatalog $catalog) {}

    public function index(): Response
    {
        /** @var User $user */
        $user = request()->user();

        return Inertia::render('AI/ProviderSettings/Index', [
            'setting' => $this->settingPayload($this->settingFor($user)),
            'available_providers' => $this->catalog->providers(),
            'available_models' => $this->catalog->modelsByProvider(),
            'permissions' => [
                'can_manage' => $user->can('manage_ai_provider_settings'),
                'can_test' => $user->can('test_ai_provider_settings'),
            ],
        ]);
    }

    public function update(UpdateAiProviderSettingRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        UpdateAiProviderSettingAction::run($user, $user, $request->payload());

        Inertia::flash('success', 'AI provider settings saved.');

        return redirect()->route('ai.provider-settings.index');
    }

    public function test(
        TestAiProviderSettingRequest $request,
        LaravelAiProviderResolver $resolver,
        AiProviderTester $tester,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $setting = TestAiProviderSettingAction::run($user, $user, $resolver, $tester);

        if ($setting->last_test_status === 'success') {
            Inertia::flash('success', 'AI provider test succeeded.');
        } else {
            Inertia::flash('error', 'AI provider test failed.');
        }

        return redirect()->route('ai.provider-settings.index');
    }

    public function destroyKey(ClearAiProviderKeyRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        ClearAiProviderKeyAction::run($user, $user);

        Inertia::flash('success', 'AI provider key cleared.');

        return redirect()->route('ai.provider-settings.index');
    }

    private function settingFor(User $user): AiProviderSetting
    {
        $setting = AiProviderSetting::query()
            ->where('user_id', $user->id)
            ->first();

        if ($setting) {
            return $setting;
        }

        return new AiProviderSetting([
            'user_id' => $user->id,
            'provider' => $this->catalog->defaultProvider(),
            'default_model' => $this->catalog->defaultModel(),
            'enabled' => false,
        ]);
    }

    private function settingPayload(AiProviderSetting $setting): array
    {
        return [
            'id' => $setting->exists ? $setting->id : null,
            'provider' => $setting->provider,
            'default_model' => $setting->default_model,
            'enabled' => (bool) $setting->enabled,
            'daily_limit_cents' => $setting->daily_limit_cents,
            'monthly_limit_cents' => $setting->monthly_limit_cents,
            'has_api_key' => $setting->exists && $setting->hasApiKey(),
            'api_key_mask' => $setting->exists ? $setting->apiKeyMask() : null,
            'tested_at' => $setting->tested_at?->toISOString(),
            'last_test_status' => $setting->last_test_status,
            'last_test_error_code' => $setting->last_test_error_code,
            'last_used_at' => $setting->last_used_at?->toISOString(),
        ];
    }
}
