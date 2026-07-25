<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Models\StudentDecision;

final class CreateStudentDecisionAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): StudentDecision
    {
        return StudentDecision::query()->create($data);
    }
}
