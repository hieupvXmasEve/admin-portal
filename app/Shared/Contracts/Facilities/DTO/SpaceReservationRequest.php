<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Facilities\DTO;

final readonly class SpaceReservationRequest
{
    public function __construct(
        public int $campusId,
        public int $roomId,
        public string $date,
        public string $startTime,
        public string $endTime,
        public ?int $requestedCapacity,
        public string $title,
        public int $requestedByUserId,
        public ?string $description = null,
        public ?int $existingReservationId = null,
    ) {}
}
