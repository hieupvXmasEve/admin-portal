<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumModule;
use Illuminate\Support\Facades\DB;

class UpdateCurriculumModuleAction
{
    /** @param array<string, mixed> $data */
    public function handle(CurriculumModule $curriculumModule, array $data): CurriculumModule
    {
        return DB::transaction(function () use ($curriculumModule, $data): CurriculumModule {
            $curriculumModule->update($data);

            return $curriculumModule;
        });
    }
}
