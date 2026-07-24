<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Institution;

use App\Shared\Contracts\Institution\DTO\CampusReference;

interface CampusReferenceReader
{
    public function find(int $campusId): ?CampusReference;

    /**
     * @return list<CampusReference>
     */
    public function all(): array;
}
