<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Actions;

use App\Modules\Facilities\Models\Building;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreateBuildingAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): Building
    {
        Log::info('Creating a new building', $data);

        $building = Building::create($data);

        self::invalidateCaches();

        return $building;
    }

    private static function invalidateCaches(): void
    {
        Cache::tags(['campuses', 'campuses.show'])->flush();
        Cache::tags(['buildings'])->flush();
    }
}
