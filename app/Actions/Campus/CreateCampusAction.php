<?php

declare(strict_types=1);

namespace App\Actions\Campus;

use App\Constants\CampusRoutes;
use App\Models\Campus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreateCampusAction
{
    public function execute(array $data): Campus
    {
        Log::info('Creating a new campus', $data);

        $campus = Campus::create($data);

        $this->invalidateCaches();

        return $campus;
    }

    private function invalidateCaches(): void
    {
        Cache::tags(['campuses'])->flush();
        Cache::tags(['campuses.index'])->flush();
        Cache::tags(['campuses.show'])->flush();
        Cache::tags(['campuses.api'])->flush();
    }
}
