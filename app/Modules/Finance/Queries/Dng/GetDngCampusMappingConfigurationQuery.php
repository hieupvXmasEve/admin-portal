<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngCampusMapping;

class GetDngCampusMappingConfigurationQuery
{
    /**
     * @return array{
     *     campus: array{id: int, name: string, code: string},
     *     mapping: array{provider_code: string, updated_at: string|null}|null
     * }
     */
    public function handle(Campus $campus): array
    {
        $mapping = DngCampusMapping::query()->where('campus_id', $campus->id)->first();

        return [
            'campus' => [
                'id' => (int) $campus->id,
                'name' => (string) $campus->name,
                'code' => (string) $campus->code,
            ],
            'mapping' => $mapping === null ? null : [
                'provider_code' => $mapping->provider_code,
                'updated_at' => $mapping->updated_at?->toIso8601String(),
            ],
        ];
    }
}
