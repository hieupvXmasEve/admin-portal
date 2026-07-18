<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;

final class EloquentStudentCollectionEligibilityReader implements StudentCollectionEligibilityReader
{
    /**
     * @param  list<'egc'|'tuition'>  $collectionPurposes
     * @return list<int>
     */
    public function eligibleStudentIds(array $collectionPurposes, ?int $campusId): array
    {
        $statuses = match ($collectionPurposes) {
            ['egc'] => ['intake_pre_uni_gc'],
            ['tuition'] => ['intake_course'],
            default => Student::FINANCIAL_STATUSES,
        };

        return Student::query()
            ->whereIn('status', $statuses)
            ->when($campusId !== null, fn ($query) => $query->where('campus_id', $campusId))
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }
}
