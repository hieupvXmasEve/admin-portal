<?php

declare(strict_types=1);

use App\Support\ThemeConfig;
use Illuminate\Support\Facades\Log;

it('defaults to the green theme preset', function () {
    config(['theme.active' => 'green']);

    expect(ThemeConfig::activeName())->toBe('green')
        ->and(ThemeConfig::variables()['light']['--primary'])->toBe('oklch(0.527 0.154 150.069)');
});

it('resolves the orange theme preset from config', function () {
    config(['theme.active' => 'orange']);

    expect(ThemeConfig::activeName())->toBe('orange')
        ->and(ThemeConfig::variables()['light']['--primary'])->toBe('oklch(0.627 0.19 48)')
        ->and(ThemeConfig::variables()['dark']['--primary'])->toBe('oklch(0.52 0.14 46)');
});

it('resolves every campus brand preset dropped into config/themes', function (string $theme, string $lightPrimary, string $darkPrimary) {
    config(['theme.active' => $theme]);

    expect(ThemeConfig::activeName())->toBe($theme)
        ->and(ThemeConfig::variables()['light']['--primary'])->toBe($lightPrimary)
        ->and(ThemeConfig::variables()['dark']['--primary'])->toBe($darkPrimary);
})->with([
    ['knu', 'oklch(0.505 0.213 27.518)', 'oklch(0.444 0.177 26.899)'],
    ['jinan', 'oklch(0.511 0.096 186.391)', 'oklch(0.437 0.078 188.216)'],
    ['gachon', 'oklch(0.5 0.134 242.749)', 'oklch(0.443 0.11 240.79)'],
]);

it('falls back to green for unknown theme values', function () {
    Log::spy();

    config(['theme.active' => 'purple']);

    expect(ThemeConfig::activeName())->toBe('green')
        ->and(ThemeConfig::variables()['light']['--primary'])->toBe('oklch(0.527 0.154 150.069)');

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('Invalid APP_THEME value; falling back to default.', [
            'requested' => 'purple',
            'default' => 'green',
        ]);
});

it('injects active theme variables into the app shell', function () {
    config(['theme.active' => 'orange']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('data-theme="orange"', false)
        ->assertSee('--primary: oklch(0.627 0.19 48);', false);
});

it('keeps the green shell unchanged when green is active', function () {
    config(['theme.active' => 'green']);

    $this->get('/login')
        ->assertOk()
        ->assertSee('data-theme="green"', false)
        ->assertSee('--primary: oklch(0.527 0.154 150.069);', false);
});
