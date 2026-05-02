<?php

declare(strict_types=1);

namespace App\Actions\Campus;

use App\Models\Campus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated Logic moved to App\Modules\Academic\Actions\UpdateCampusAction. Remove after 2026-06-01.
 */
class UpdateCampusAction
{
    public function execute(Campus $campus, array $data): Campus
    {
        Log::info("Updating campus {$campus->id}", $data);

        $campus->update($data);

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
