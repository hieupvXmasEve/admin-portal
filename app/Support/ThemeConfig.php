<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

class ThemeConfig
{
    /**
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function variables(): array
    {
        $preset = config('theme.presets.'.static::activeName());

        if (! is_array($preset)) {
            $preset = config('theme.presets.'.config('theme.default', 'green'), []);
        }

        /** @var array{light: array<string, string>, dark: array<string, string>} $preset */
        return $preset;
    }

    public static function activeName(): string
    {
        $requested = (string) config('theme.active', config('theme.default', 'green'));
        $presets = array_keys(config('theme.presets', []));

        if (in_array($requested, $presets, true)) {
            return $requested;
        }

        if ($requested !== (string) config('theme.default', 'green')) {
            static::warnInvalidTheme($requested);
        }

        return (string) config('theme.default', 'green');
    }

    protected static function warnInvalidTheme(string $requested): void
    {
        if (app()->environment('production')) {
            return;
        }

        Log::warning('Invalid APP_THEME value; falling back to default.', [
            'requested' => $requested,
            'default' => config('theme.default', 'green'),
        ]);
    }
}
