<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Shared\Contracts\Platform\SystemConfigurationReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class SystemConfigurationStore implements SystemConfigurationReader
{
    private const CACHE_KEY = 'system_config';

    private const CONFIG_FILE = 'system_config.json';

    /** @var list<string> */
    private const PUBLIC_KEYS = [
        'app_name',
        'logo_full',
        'logo_text',
        'copyright_text',
        'country',
        'survey_enabled',
    ];

    /** @var array<string, string> */
    private const UPLOAD_TARGETS = [
        'logo_full' => '/storage/branding/logo-full.png',
        'logo_text' => '/storage/branding/logo-text.svg',
    ];

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> $configuration */
        $configuration = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->load());

        return $configuration;
    }

    /**
     * @return array<string, mixed>
     */
    public function public(): array
    {
        return Arr::only($this->all(), self::PUBLIC_KEYS);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    public function publicValue(string $key): mixed
    {
        if (! in_array($key, self::PUBLIC_KEYS, true)) {
            return null;
        }

        return $this->public()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(array $attributes): array
    {
        $configuration = array_merge($this->all(), $attributes);

        try {
            $encoded = json_encode($configuration, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode system configuration.', previous: $exception);
        }

        if (! Storage::put(self::CONFIG_FILE, $encoded)) {
            throw new RuntimeException('Unable to persist system configuration.');
        }

        Cache::forget(self::CACHE_KEY);

        return $this->all();
    }

    /**
     * @return array{path: string, stored_path: string, cache_bust: int}
     */
    public function upload(UploadedFile $file, string $configurationKey): array
    {
        $targetPath = $this->get($configurationKey, self::UPLOAD_TARGETS[$configurationKey] ?? null);
        if (! is_string($targetPath) || ! str_starts_with($targetPath, '/storage/')) {
            throw new RuntimeException("Invalid configuration key for file upload: {$configurationKey}");
        }

        $storagePath = Str::after($targetPath, '/storage/');
        if ($storagePath === '' || str_contains($storagePath, '..')) {
            throw new RuntimeException("Invalid configuration file path: {$configurationKey}");
        }

        $directory = dirname($storagePath);
        $filename = basename($storagePath);

        Storage::disk('public')->makeDirectory($directory);

        $stored = Storage::disk('public')->putFileAs($directory, $file, $filename);

        if ($stored === false) {
            throw new RuntimeException('Unable to store system configuration file.');
        }

        return [
            'path' => $targetPath,
            'stored_path' => '/storage/'.$stored,
            'cache_bust' => time(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if (! Storage::exists(self::CONFIG_FILE)) {
            return $this->defaults();
        }

        try {
            $configuration = json_decode(Storage::get(self::CONFIG_FILE), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::error('Unable to parse system configuration JSON.', ['exception' => $exception]);

            return $this->defaults();
        }

        return is_array($configuration) ? $configuration : $this->defaults();
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'app_name' => (string) config('app.name'),
            'logo_full' => self::UPLOAD_TARGETS['logo_full'],
            'logo_text' => self::UPLOAD_TARGETS['logo_text'],
            'copyright_text' => '© '.date('Y').' Asia Vietnam University. All rights reserved.',
            'country' => 'Việt Nam',
        ];
    }
}
