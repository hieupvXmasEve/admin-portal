<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\LegacyTranscriptOutcome;

interface LegacyTranscriptOutcomeReader
{
    /**
     * @param  array{student_id?: int, student_ids?: list<int>, semester_id?: int}  $scope
     * @return list<LegacyTranscriptOutcome>
     */
    public function finalizedOutcomes(array $scope): array;
}
