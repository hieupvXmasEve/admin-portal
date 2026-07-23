<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumModule;
use App\Models\CurriculumVersion;
use Illuminate\Support\Facades\DB;

class DetachCurriculumModulesAction
{
    /** @param list<int> $moduleIds */
    public function handle(CurriculumVersion $curriculumVersion, array $moduleIds): void
    {
        DB::transaction(fn () => CurriculumModule::query()
            ->where('curriculum_version_id', $curriculumVersion->id)
            ->whereIn('module_id', $moduleIds)
            ->delete());
    }
}
