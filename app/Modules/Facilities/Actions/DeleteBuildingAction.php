<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Actions;

use App\Models\Building;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeleteBuildingAction
{
    public static function run(Building $building): void
    {
        Log::warning("Deleting building {$building->id}");

        $building->delete();

        self::invalidateCaches();
    }

    private static function invalidateCaches(): void
    {
        Cache::tags(['campuses', 'campuses.show'])->flush();
        Cache::tags(['buildings'])->flush();
    }
}
