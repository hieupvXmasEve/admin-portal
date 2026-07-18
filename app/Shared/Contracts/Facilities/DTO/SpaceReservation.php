<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Facilities\DTO;

final readonly class SpaceReservation
{
    public function __construct(
        public int $reservationId,
        public int $roomId,
        public int $campusId,
        public int $capacity,
    ) {}
}
