<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\SystemSetting;
use App\Shared\Contracts\Platform\SystemConfigurationReader;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SystemConfigurationStore implements SystemConfigurationReader
{
    private const CACHE_KEY = 'platform.system_configuration.snapshot.v1';

    public function __construct(private readonly SystemConfigurationDefinition $definition) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        /** @var array<string, mixed> $configuration */
        $configuration = $this->cache()->rememberForever(self::CACHE_KEY, fn (): array => $this->loadSnapshot());

        return $configuration;
    }

    /**
     * @return array<string, mixed>
     */
    public function public(): array
    {
        return Arr::only($this->all(), $this->definition->publicKeys());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    public function publicValue(string $key): mixed
    {
        if (! in_array($key, $this->definition->publicKeys(), true)) {
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
        $attributes = $this->definition->validate($attributes);
        $this->assertReady();

        foreach ($attributes as $key => $value) {
            $updated = SystemSetting::query()
                ->where('key', $key)
                ->update([
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new RuntimeException("Unable to persist required system configuration key: {$key}");
            }
        }

        DB::afterCommit(fn (): bool => $this->cache()->forget(self::CACHE_KEY));

        return $this->loadSnapshot();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSnapshot(): array
    {
        /** @var array<string, mixed> $configuration */
        $configuration = SystemSetting::query()
            ->orderBy('key')
            ->get()
            ->mapWithKeys(static fn (SystemSetting $setting): array => [$setting->key => $setting->value])
            ->all();

        $this->assertReady($configuration);

        return $configuration;
    }

    /**
     * @param  array<string, mixed>|null  $configuration
     */
    private function assertReady(?array $configuration = null): void
    {
        $keys = $configuration === null
            ? SystemSetting::query()->pluck('key')->all()
            : array_keys($configuration);
        $missing = array_values(array_diff($this->definition->requiredKeys(), $keys));

        if ($missing !== []) {
            throw new RuntimeException(
                'System configuration is not ready; required keys are missing: '.implode(', ', $missing).'.',
            );
        }
    }

    private function cache(): Repository
    {
        $store = (string) config('cache.default');
        $driver = (string) config("cache.stores.{$store}.driver");

        if (! app()->runningUnitTests() && in_array($driver, ['array', 'file', 'null', 'octane'], true)) {
            throw new RuntimeException(
                "System configuration requires a shared cache store; '{$driver}' is not supported.",
            );
        }

        return Cache::store($store);
    }
}
