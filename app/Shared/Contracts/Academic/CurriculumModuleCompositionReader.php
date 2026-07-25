<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\CurriculumModuleComposition;

interface CurriculumModuleCompositionReader
{
    /** @return list<CurriculumModuleComposition> */
    public function forCurriculumVersion(?int $curriculumVersionId): array;
}
