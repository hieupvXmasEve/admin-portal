<?php

declare(strict_types=1);

namespace App\Actions\Building;

use App\Models\Building;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeleteBuildingAction
{
    public function execute(Building $building): void
    {
        Log::warning("Deleting building {$building->id}");

        $building->delete();

        $this->invalidateCaches();
    }

    private function invalidateCaches(): void
    {
        Cache::tags(['campuses', 'campuses.show'])->flush();
        Cache::tags(['buildings'])->flush();
    }
}
