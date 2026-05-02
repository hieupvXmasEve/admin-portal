<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\Campus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreateCampusAction
{
    public static function run(array $data): Campus
    {
        Log::info('Creating a new campus', $data);

        $campus = Campus::create($data);

        self::invalidateCaches();

        return $campus;
    }

    private static function invalidateCaches(): void
    {
        Cache::tags(['campuses'])->flush();
        Cache::tags(['campuses.index'])->flush();
        Cache::tags(['campuses.show'])->flush();
        Cache::tags(['campuses.api'])->flush();
    }
}
