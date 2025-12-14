<?php

declare(strict_types=1);

namespace App\Actions\Campus;

use App\Models\Campus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeleteCampusAction
{
    public function execute(Campus $campus): void
    {
        Log::warning("Deleting campus {$campus->id}");

        $campus->delete();

        $this->invalidateCaches();
    }

    private function invalidateCaches(): void
    {
        Cache::tags(['campuses'])->flush();
        Cache::tags(['campuses.index'])->flush();
        Cache::tags(['campuses.show'])->flush();
        Cache::tags(['campuses.api'])->flush();
    }
}
