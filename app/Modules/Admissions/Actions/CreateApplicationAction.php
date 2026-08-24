<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\StudentApplication;

final class CreateApplicationAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): StudentApplication
    {
        // High-school / national-exam subject scores live in a relation, not on
        // the applications table — split them off before mass-assigning.
        $scores = $data['academic_scores'] ?? [];
        unset($data['academic_scores']);

        $application = StudentApplication::query()->create($data);

        if ($scores !== []) {
            $application->academicScores()->createMany($scores);
        }

        return $application;
    }
}
