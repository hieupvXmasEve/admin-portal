<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

use Carbon\CarbonImmutable;

readonly class AcademicPeriodReference
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?CarbonImmutable $start_date,
        public ?CarbonImmutable $end_date,
        public ?CarbonImmutable $registration_start_date,
        public ?CarbonImmutable $registration_end_date,
        public bool $is_current,
        /** @var array<string, mixed> */
        public array $payload = [],
    ) {}
}
