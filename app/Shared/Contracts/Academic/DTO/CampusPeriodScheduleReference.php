<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

use Carbon\CarbonImmutable;

readonly class CampusPeriodScheduleReference
{
    public function __construct(
        public int $academic_period_id,
        public int $campus_id,
        public ?CarbonImmutable $operating_start_date,
        public ?CarbonImmutable $operating_end_date,
        public ?CarbonImmutable $registration_start_date,
        public ?CarbonImmutable $registration_end_date,
    ) {}
}
