<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Actions;

use App\Models\Building;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateBuildingAction
{
    /** @param array<string, mixed> $data */
    public static function run(Building $building, array $data): Building
    {
        Log::info("Updating building {$building->id}", $data);

        $building->update($data);

        self::invalidateCaches();

        return $building;
    }

    private static function invalidateCaches(): void
    {
        Cache::tags(['campuses', 'campuses.show'])->flush();
        Cache::tags(['buildings'])->flush();
    }
}
