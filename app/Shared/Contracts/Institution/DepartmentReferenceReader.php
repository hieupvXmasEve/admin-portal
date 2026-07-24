<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Institution;

use App\Shared\Contracts\Institution\DTO\DepartmentReference;

interface DepartmentReferenceReader
{
    public function find(int $departmentId): ?DepartmentReference;

    /**
     * @return list<int>
     */
    public function activeMemberUserIds(int $departmentId): array;

    /**
     * @return list<DepartmentReference>
     */
    public function allActive(): array;
}
