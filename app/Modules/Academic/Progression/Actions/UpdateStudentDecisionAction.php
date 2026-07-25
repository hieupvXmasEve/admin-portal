<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Models\StudentDecision;

final class UpdateStudentDecisionAction
{
    /** @param array<string, mixed> $data */
    public static function run(int $decisionId, array $data): StudentDecision
    {
        $decision = StudentDecision::query()->findOrFail($decisionId);
        $decision->update($data);

        return $decision->refresh();
    }
}
