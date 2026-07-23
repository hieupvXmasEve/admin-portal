<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumUnit;
use Illuminate\Support\Facades\DB;

class DeleteCurriculumUnitAction
{
    public function handle(CurriculumUnit $curriculumUnit): void
    {
        DB::transaction(fn () => $curriculumUnit->delete());
    }
}
