<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class AcademicChargeSourceData
{
    /**
     * @param  array<string, mixed>  $facts
     */
    public function __construct(
        public int $id,
        public int $student_id,
        public int $semester_id,
        public int $campus_id,
        public string $source_kind,
        public string $source_ref,
        public string $obligation_type,
        public array $facts,
        public ?string $status = null,
        public ?string $hq_fee_status = null,
    ) {}
}
