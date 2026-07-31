<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface StudentLifecycleMatcher
{
    /**
     * Returns the supplied student identifiers whose primary enrollment has
     * one of the requested lifecycle statuses.
     *
     * @param  list<int>  $studentIds
     * @param  list<string>  $statuses
     * @return list<int>
     */
    public function matchingStudentIds(array $studentIds, array $statuses): array;
}
