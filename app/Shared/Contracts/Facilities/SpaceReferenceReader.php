<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Facilities;

use App\Shared\Contracts\Facilities\DTO\SpaceReference;

interface SpaceReferenceReader
{
    /** @return list<SpaceReference> */
    public function forCampus(?int $campusId): array;
}
