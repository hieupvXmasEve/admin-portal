<?php

declare(strict_types=1);

namespace App\Modules\Institution\Queries;

use App\Models\Department;
use Illuminate\Database\Eloquent\Collection;

class ListDepartmentsQuery
{
    /**
     * @return Collection<int, Department>
     */
    public function handle(): Collection
    {
        return Department::query()
            ->withCount('memberships')
            ->get();
    }
}
