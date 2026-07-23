<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumUnit;
use Illuminate\Support\Facades\DB;

class UpdateCurriculumUnitAction
{
    /** @param array<string, mixed> $data */
    public function handle(CurriculumUnit $curriculumUnit, array $data): CurriculumUnit
    {
        return DB::transaction(function () use ($curriculumUnit, $data): CurriculumUnit {
            $curriculumUnit->update($data);

            return $curriculumUnit;
        });
    }
}
