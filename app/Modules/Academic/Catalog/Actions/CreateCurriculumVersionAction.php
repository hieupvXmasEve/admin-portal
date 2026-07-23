<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumVersion;
use Illuminate\Support\Facades\DB;

class CreateCurriculumVersionAction
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): CurriculumVersion
    {
        return DB::transaction(fn (): CurriculumVersion => CurriculumVersion::query()->create($data));
    }
}
