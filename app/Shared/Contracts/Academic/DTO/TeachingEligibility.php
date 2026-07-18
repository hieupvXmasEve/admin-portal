<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

/**
 * Workforce's current answer to whether a Faculty Member may teach.
 *
 * This is intentionally not an assignment reservation. Delivery combines this
 * fact with its own workload, campus, and timetable state at assignment time.
 */
final readonly class TeachingEligibility
{
    public function __construct(
        public int $lecturerId,
        public int $campusId,
        public ?int $maxTeachingHoursPerWeek,
        public bool $isEligible,
        public string $reason,
    ) {}
}
