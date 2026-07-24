<?php

declare(strict_types=1);

namespace App\Modules\Institution\Support;

use App\Models\Department;
use App\Shared\Contracts\Institution\DepartmentReferenceReader;
use App\Shared\Contracts\Institution\DTO\DepartmentReference;

class InstitutionDepartmentReferenceReader implements DepartmentReferenceReader
{
    public function find(int $departmentId): ?DepartmentReference
    {
        return $this->reference(Department::query()->find($departmentId));
    }

    public function activeMemberUserIds(int $departmentId): array
    {
        return Department::query()
            ->find($departmentId)
            ?->members()
            ->where('is_active', true)
            ->pluck('user_id')
            ->map(static fn (int|string $userId): int => (int) $userId)
            ->unique()
            ->values()
            ->all() ?? [];
    }

    /**
     * @return list<DepartmentReference>
     */
    public function allActive(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Department $department): DepartmentReference => $this->reference($department))
            ->all();
    }

    private function reference(?Department $department): ?DepartmentReference
    {
        if ($department === null) {
            return null;
        }

        return new DepartmentReference(
            id: (int) $department->id,
            name: (string) $department->name,
            code: (string) $department->code,
        );
    }
}
