<?php

declare(strict_types=1);

namespace App\Modules\Institution\Support;

use App\Models\Department;
use App\Shared\Contracts\Institution\DepartmentReferenceReader;
use App\Shared\Contracts\Institution\DTO\DepartmentReference;

class InstitutionDepartmentReferenceReader implements DepartmentReferenceReader
{
    /**
     * @return list<DepartmentReference>
     */
    public function allActive(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Department $department): DepartmentReference => new DepartmentReference(
                id: (int) $department->id,
                name: (string) $department->name,
                code: (string) $department->code,
            ))
            ->all();
    }
}
