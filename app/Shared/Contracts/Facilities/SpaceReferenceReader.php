<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Facilities;

use App\Shared\Contracts\Facilities\DTO\SpaceReference;

interface SpaceReferenceReader
{
    /** @return list<SpaceReference> */
    public function forCampus(?int $campusId): array;

    /**
     * Rooms a caller may actually schedule into: bookable and currently
     * available. Narrower than {@see self::forCampus()}, which also returns
     * rooms under maintenance or closed to booking.
     *
     * @return list<SpaceReference>
     */
    public function bookableForCampus(int $campusId): array;
}
