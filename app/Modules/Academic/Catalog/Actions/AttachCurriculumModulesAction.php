<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumModule;
use App\Models\CurriculumVersion;
use Illuminate\Support\Facades\DB;

class AttachCurriculumModulesAction
{
    /** @param list<array<string, mixed>> $modules */
    public function handle(CurriculumVersion $curriculumVersion, array $modules): void
    {
        DB::transaction(function () use ($curriculumVersion, $modules): void {
            foreach ($modules as $moduleData) {
                CurriculumModule::query()->updateOrCreate(
                    [
                        'curriculum_version_id' => $curriculumVersion->id,
                        'module_id' => $moduleData['module_id'],
                    ],
                    [
                        'year_level' => $moduleData['year_level'] ?? null,
                        'semester_number' => $moduleData['semester_number'] ?? null,
                        'is_required' => $moduleData['is_required'] ?? true,
                        'group_name' => $moduleData['group_name'] ?? null,
                        'order' => $moduleData['order'],
                        'note' => $moduleData['note'] ?? null,
                    ],
                );
            }
        });
    }
}
