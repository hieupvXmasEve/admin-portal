<?php

declare(strict_types=1);

namespace App\Actions\Building;

use App\Models\Building;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreateBuildingAction
{
    public function execute(array $data): Building
    {
        Log::info('Creating a new building', $data);

        $building = Building::create($data);

        $this->invalidateCaches();

        return $building;
    }

    private function invalidateCaches(): void
    {
        Cache::tags(['campuses', 'campuses.show'])->flush();
        Cache::tags(['buildings'])->flush();
    }
}
