<?php

declare(strict_types=1);

namespace App\Actions\Building;

use App\Models\Building;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateBuildingAction
{
    public function execute(Building $building, array $data): Building
    {
        Log::info("Updating building {$building->id}", $data);

        $building->update($data);

        $this->invalidateCaches();

        return $building;
    }

    private function invalidateCaches(): void
    {
        Cache::tags(['campuses', 'campuses.show'])->flush();
        Cache::tags(['buildings'])->flush();
    }
}
