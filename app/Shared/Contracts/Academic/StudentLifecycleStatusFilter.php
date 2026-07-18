<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

interface StudentLifecycleStatusFilter
{
    /**
     * @param  Builder<Student>  $students
     * @param  list<string>  $statuses
     */
    public function apply(Builder $students, array $statuses): void;
}
