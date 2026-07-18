<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Institution;

use App\Shared\Contracts\Institution\DTO\DepartmentReference;

interface DepartmentReferenceReader
{
    /**
     * @return list<DepartmentReference>
     */
    public function allActive(): array;
}
