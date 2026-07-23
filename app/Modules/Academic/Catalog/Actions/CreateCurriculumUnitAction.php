<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumUnit;
use Illuminate\Support\Facades\DB;

class CreateCurriculumUnitAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): CurriculumUnit
    {
        return DB::transaction(fn (): CurriculumUnit => CurriculumUnit::query()->create($data));
    }
}
