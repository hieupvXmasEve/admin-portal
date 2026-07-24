<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\CurriculumGraduationRequirement;

interface CurriculumGraduationRequirementsReader
{
    /** @return list<CurriculumGraduationRequirement> */
    public function forCurriculumVersion(?int $curriculumVersionId): array;
}
