<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Support\SystemConfigurationAuditLogger;
use App\Modules\Platform\Support\SystemConfigurationDefinition;
use App\Modules\Platform\Support\SystemConfigurationStore;
use App\Shared\Contracts\Platform\SystemConfigurationWriter;
use Illuminate\Support\Facades\DB;

final class UpdateSystemConfigurationAction implements SystemConfigurationWriter
{
    public function __construct(
        private readonly SystemConfigurationStore $configuration,
        private readonly SystemConfigurationDefinition $definition,
        private readonly SystemConfigurationAuditLogger $auditLogger,
    ) {}

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
        $attributes = $this->definition->validate($attributes);

        return DB::transaction(function () use ($attributes): array {
            $before = $this->configuration->all();
            $updated = $this->configuration->update($attributes);

            $changedKeys = array_keys(array_filter(
                $attributes,
                fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value,
                ARRAY_FILTER_USE_BOTH,
            ));

            if ($changedKeys !== []) {
                $this->auditLogger->record('updated', $changedKeys);
            }

            return $updated;
        });
    }
}
