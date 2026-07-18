<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Facilities;

use App\Shared\Contracts\Facilities\DTO\SpaceAvailability;
use App\Shared\Contracts\Facilities\DTO\SpaceReservationRequest;

interface SpaceAvailabilityReader
{
    public function check(SpaceReservationRequest $request): SpaceAvailability;
}
