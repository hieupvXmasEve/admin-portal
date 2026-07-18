<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Facilities;

use App\Shared\Contracts\Facilities\DTO\SpaceReservation;
use App\Shared\Contracts\Facilities\DTO\SpaceReservationRequest;

interface SpaceReservationContract
{
    public function reserve(SpaceReservationRequest $request): SpaceReservation;
}
