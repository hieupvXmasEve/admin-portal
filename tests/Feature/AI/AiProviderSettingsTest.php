<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function encodedAuditProperties(object $audit): string
{
    $properties = is_string($audit->properties)
        ? json_decode($audit->properties, true, 512, JSON_THROW_ON_ERROR)
        : $audit->properties;

    return json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}

function openRouterModelsResponse(): array
{
    return [
        'data' => [
            [
                'id' => 'openrouter/auto',
                'name' => 'Auto Router',
                'architecture' => [
                    'output_modalities' => ['text'],
                ],
            ],
            [
                'id' => 'anthropic/claude-sonnet-4-5',
                'name' => 'Anthropic: Claude Sonnet 4.5',
                'architecture' => [
                    'output_modalities' => ['text'],
                ],
            ],
            [
                'id' => 'google/gemini-2.5-pro',
                'name' => 'Google: Gemini 2.5 Pro',
                'architecture' => [
                    'output_modalities' => ['text'],
                ],
            ],
            [
                'id' => 'google/gemini-2.5-flash-image',
                'name' => 'Google: Gemini 2.5 Flash Image',
                'architecture' => [
                    'output_modalities' => ['image'],
                ],
            ],
        ],
    ];
}

beforeEach(function () {
    Cache::flush();
    Http::fake([
        'https://openrouter.ai/api/v1/models*' => Http::response(openRouterModelsResponse(), 200),
    ]);

    $this->authorizedUser = User::factory()->create();
    $this->unauthorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create();

    session([
        '_token' => 'ai-provider-settings-test-token',
        'current_campus_id' => $this->campus->id,
    ]);

    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(function (User $user): array {
            if ($user->id === $this->authorizedUser->id) {
                return [
                    'view_ai_provider_settings',
                    'manage_ai_provider_settings',
                    'test_ai_provider_settings',
                ];
            }

            return [];
        });

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('renders the current staff AI provider settings without exposing secrets', function () {
    $this->actingAs($this->authorizedUser)
        ->get(route('ai.provider-settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AI/ProviderSettings/Index')
            ->has('available_providers')
            ->has('available_models')
            ->where('setting.provider', 'openai')
            ->where('setting.default_model', 'gpt-4o-mini')
            ->where('setting.enabled', false)
            ->where('setting.has_api_key', false)
            ->where('setting.api_key_mask', null)
            ->where('permissions.can_manage', true)
            ->where('permissions.can_test', true)
            ->missing('setting.api_key')
            ->missing('setting.encrypted_api_key'));
});

it('exposes OpenRouter provider and current text models from the provider model API', function () {
    Http::fake([
        'https://openrouter.ai/api/v1/models*' => Http::response(openRouterModelsResponse(), 200),
    ]);

    $response = $this->actingAs($this->authorizedUser)
        ->get(route('ai.provider-settings.index'))
        ->assertOk();

    $page = $response->viewData('page');
    $props = json_decode(json_encode($page['props'], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

    $openRouterProvider = collect($props['available_providers'])->firstWhere('id', 'openrouter');
    $openRouterModelIds = collect($props['available_models']['openrouter'] ?? [])->pluck('id')->all();

    expect($openRouterProvider)->toMatchArray([
        'id' => 'openrouter',
        'label' => 'OpenRouter',
    ])
        ->and($openRouterModelIds)->toContain('openai/gpt-4o-mini')
        ->and($openRouterModelIds)->toContain('openrouter/free')
        ->and($openRouterModelIds)->toContain('openrouter/auto')
        ->and($openRouterModelIds)->toContain('anthropic/claude-sonnet-4-5')
        ->and($openRouterModelIds)->toContain('google/gemini-2.5-pro')
        ->and($openRouterModelIds)->not->toContain('google/gemini-2.5-flash-image');
});

it('uses a searchable combobox for the default model selector', function () {
    $component = file_get_contents(resource_path('js/pages/AI/ProviderSettings/Index.vue'));

    expect($component)
        ->toContain("from '@/components/ui/combobox'")
        ->toContain('ComboboxInput')
        ->toContain('Search models');
});

it('denies provider settings access to staff without AI settings permissions', function () {
    $this->actingAs($this->unauthorizedUser)
        ->get(route('ai.provider-settings.index'))
        ->assertForbidden();

    $this->actingAs($this->unauthorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->put(route('ai.provider-settings.update'), [
            'provider' => 'openai',
            'default_model' => 'gpt-4o-mini',
            'api_key' => 'sk-denied-secret',
            'enabled' => true,
            'daily_limit_cents' => 1000,
            'monthly_limit_cents' => 30000,
        ])
        ->assertForbidden();
});

it('saves encrypted provider settings, returns only a masked key, and writes redacted audit data', function () {
    $secret = 'sk-live-secret-abc123';

    $response = $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->from(route('ai.provider-settings.index'))
        ->put(route('ai.provider-settings.update'), [
            'provider' => 'openai',
            'default_model' => 'gpt-4o-mini',
            'api_key' => $secret,
            'enabled' => true,
            'daily_limit_cents' => 1000,
            'monthly_limit_cents' => 30000,
        ]);

    $response
        ->assertRedirect(route('ai.provider-settings.index'))
        ->assertInertiaFlash('success', 'AI provider settings saved.');

    $row = DB::table('ai_provider_settings')
        ->where('user_id', $this->authorizedUser->id)
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->provider)->toBe('openai')
        ->and($row->default_model)->toBe('gpt-4o-mini')
        ->and((bool) $row->enabled)->toBeTrue()
        ->and($row->encrypted_api_key)->not->toBe($secret)
        ->and(Crypt::decryptString($row->encrypted_api_key))->toBe($secret);

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.provider-settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('setting.has_api_key', true)
            ->where('setting.api_key_mask', 'sk...c123')
            ->missing('setting.api_key')
            ->missing('setting.encrypted_api_key'));

    $audit = DB::table('activity_log')
        ->where('description', 'AI provider settings updated')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();

    $auditProperties = encodedAuditProperties($audit);

    expect($auditProperties)
        ->toContain('"provider":"openai"')
        ->toContain('"key_changed":true')
        ->not->toContain($secret)
        ->not->toContain('encrypted_api_key')
        ->not->toContain('authorization_header')
        ->not->toContain('raw_provider_request')
        ->not->toContain('raw_provider_response');

});

it('rejects unsupported provider and model choices before persistence', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->from(route('ai.provider-settings.index'))
        ->put(route('ai.provider-settings.update'), [
            'provider' => 'unsupported',
            'default_model' => 'not-a-model',
            'api_key' => 'sk-unsupported-secret',
            'enabled' => true,
            'daily_limit_cents' => 1000,
            'monthly_limit_cents' => 30000,
        ])
        ->assertRedirect(route('ai.provider-settings.index'))
        ->assertSessionHasErrors(['provider', 'default_model']);

    expect(DB::table('ai_provider_settings')->count())->toBe(0);
});

it('validates cost limits before persistence', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->from(route('ai.provider-settings.index'))
        ->put(route('ai.provider-settings.update'), [
            'provider' => 'openai',
            'default_model' => 'gpt-4o-mini',
            'api_key' => 'sk-limit-secret',
            'enabled' => true,
            'daily_limit_cents' => 5000,
            'monthly_limit_cents' => 1000,
        ])
        ->assertRedirect(route('ai.provider-settings.index'))
        ->assertSessionHasErrors(['monthly_limit_cents']);

    expect(DB::table('ai_provider_settings')->count())->toBe(0);
});

it('tests OpenRouter with a real chat completion health request and stores only safe metadata', function () {
    Http::fake([
        'https://openrouter.ai/api/v1/models*' => Http::response(openRouterModelsResponse(), 200),
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'ok',
                    ],
                ],
            ],
            'usage' => [
                'prompt_tokens' => 12,
                'completion_tokens' => 1,
                'total_tokens' => 13,
            ],
        ], 200),
    ]);

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->put(route('ai.provider-settings.update'), [
            'provider' => 'openrouter',
            'default_model' => 'openrouter/free',
            'api_key' => 'sk-openrouter-test-secret',
            'enabled' => true,
            'daily_limit_cents' => null,
            'monthly_limit_cents' => null,
        ]);

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->from(route('ai.provider-settings.index'))
        ->post(route('ai.provider-settings.test'))
        ->assertRedirect(route('ai.provider-settings.index'))
        ->assertInertiaFlash('success', 'AI provider test succeeded.');

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-openrouter-test-secret')
            && $request->hasHeader('Content-Type', 'application/json')
            && ($payload['model'] ?? null) === 'openrouter/free'
            && ($payload['max_tokens'] ?? null) === 8
            && ($payload['temperature'] ?? null) === 0
            && ($payload['messages'][0]['role'] ?? null) === 'system'
            && ($payload['messages'][1]['role'] ?? null) === 'user'
            && str_contains((string) ($payload['messages'][1]['content'] ?? ''), 'Swinx AI provider health check')
            && ! str_contains($encodedPayload, 'student_data')
            && ! str_contains($encodedPayload, 'finance_data')
            && ! str_contains($encodedPayload, 'business_prompt');
    });

    $row = DB::table('ai_provider_settings')
        ->where('user_id', $this->authorizedUser->id)
        ->first();

    expect($row->tested_at)->not->toBeNull()
        ->and($row->last_test_status)->toBe('success')
        ->and($row->last_test_error_code)->toBeNull();

    $audit = DB::table('activity_log')
        ->where('description', 'AI provider test attempted')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();

    $auditProperties = encodedAuditProperties($audit);

    expect($auditProperties)
        ->toContain('"provider":"openrouter"')
        ->toContain('"model":"openrouter/free"')
        ->toContain('"status":"success"')
        ->toContain('"health_payload":"static_non_business_chat_completion"')
        ->not->toContain('sk-openrouter-test-secret')
        ->not->toContain('student_data')
        ->not->toContain('finance_data')
        ->not->toContain('business_prompt')
        ->not->toContain('raw_provider_request')
        ->not->toContain('raw_provider_response');
});

it('maps OpenRouter authentication failures to a safe provider test error code', function () {
    Http::fake([
        'https://openrouter.ai/api/v1/models*' => Http::response(openRouterModelsResponse(), 200),
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'error' => [
                'code' => 401,
                'message' => 'Invalid credentials',
            ],
        ], 401),
    ]);

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->put(route('ai.provider-settings.update'), [
            'provider' => 'openrouter',
            'default_model' => 'openrouter/free',
            'api_key' => 'sk-openrouter-bad-secret',
            'enabled' => true,
            'daily_limit_cents' => null,
            'monthly_limit_cents' => null,
        ]);

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->from(route('ai.provider-settings.index'))
        ->post(route('ai.provider-settings.test'))
        ->assertRedirect(route('ai.provider-settings.index'))
        ->assertInertiaFlash('error', 'AI provider test failed.');

    $row = DB::table('ai_provider_settings')
        ->where('user_id', $this->authorizedUser->id)
        ->first();

    expect($row->tested_at)->not->toBeNull()
        ->and($row->last_test_status)->toBe('failed')
        ->and($row->last_test_error_code)->toBe('provider_authentication_failed');

    $audit = DB::table('activity_log')
        ->where('description', 'AI provider test attempted')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();

    $auditProperties = encodedAuditProperties($audit);

    expect($auditProperties)
        ->toContain('"provider":"openrouter"')
        ->toContain('"model":"openrouter/free"')
        ->toContain('"status":"failed"')
        ->toContain('"error_code":"provider_authentication_failed"')
        ->not->toContain('sk-openrouter-bad-secret')
        ->not->toContain('Invalid credentials')
        ->not->toContain('raw_provider_response');
});

it('clears a stored key, disables the setting, and does not audit credential material', function () {
    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->put(route('ai.provider-settings.update'), [
            'provider' => 'openai',
            'default_model' => 'gpt-4o-mini',
            'api_key' => 'sk-clear-me-secret',
            'enabled' => true,
            'daily_limit_cents' => 1000,
            'monthly_limit_cents' => 30000,
        ]);

    $this->actingAs($this->authorizedUser)
        ->withHeader('X-CSRF-TOKEN', 'ai-provider-settings-test-token')
        ->from(route('ai.provider-settings.index'))
        ->delete(route('ai.provider-settings.key.destroy'))
        ->assertRedirect(route('ai.provider-settings.index'));

    $row = DB::table('ai_provider_settings')
        ->where('user_id', $this->authorizedUser->id)
        ->first();

    expect($row->encrypted_api_key)->toBeNull()
        ->and((bool) $row->enabled)->toBeFalse();

    $this->actingAs($this->authorizedUser)
        ->get(route('ai.provider-settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('setting.has_api_key', false)
            ->where('setting.api_key_mask', null));

    $audit = DB::table('activity_log')
        ->where('description', 'AI provider key cleared')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();

    $auditProperties = encodedAuditProperties($audit);

    expect($auditProperties)
        ->toContain('"key_cleared":true')
        ->not->toContain('sk-clear-me-secret')
        ->not->toContain('encrypted_api_key');
});
