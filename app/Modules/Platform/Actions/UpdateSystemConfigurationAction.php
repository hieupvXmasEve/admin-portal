<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Support\SystemConfigurationAuditLogger;
use App\Modules\Platform\Support\SystemConfigurationStore;
use App\Shared\Contracts\Platform\SystemConfigurationWriter;

final class UpdateSystemConfigurationAction implements SystemConfigurationWriter
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function run(array $attributes): array
    {
        return app(self::class)->update($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(array $attributes): array
    {
        $configuration = app(SystemConfigurationStore::class);
        $before = $configuration->all();
        $updated = $configuration->update($attributes);

        $changedKeys = array_keys(array_filter(
            $attributes,
            fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        app(SystemConfigurationAuditLogger::class)->record('updated', $changedKeys);

        return $updated;
    }
}
