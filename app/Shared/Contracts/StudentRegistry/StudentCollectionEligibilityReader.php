<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

interface StudentCollectionEligibilityReader
{
    /**
     * @param  list<'egc'|'tuition'>  $collectionPurposes
     * @return list<int>
     */
    public function eligibleStudentIds(array $collectionPurposes, ?int $campusId): array;
}
