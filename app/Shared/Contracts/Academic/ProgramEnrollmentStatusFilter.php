<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use Illuminate\Database\Eloquent\Builder;

/**
 * Narrows a student query to the given lifecycle statuses.
 *
 * Academic Progression owns how a student's status is derived — primary program
 * enrollment first, legacy `students.status` only when no enrollment exists — so
 * consumers outside Academic constrain a student builder through this contract
 * rather than reproducing that precedence.
 */
interface ProgramEnrollmentStatusFilter
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $students
     * @param  list<string>  $statuses
     */
    public function apply(Builder $students, array $statuses): void;
}
