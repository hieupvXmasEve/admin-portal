<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class AcademicEgcBlockData
{
    public const RESULT_PENDING = 'pending';

    public const RESULT_PASS = 'pass';

    public const RESULT_FAIL = 'fail';

    public function __construct(
        public int $id,
        public int $student_id,
        public int $semester_id,
        public int $block_number,
        public int $level_number,
        public string $result,
        public bool $is_retake,
        public ?float $attendance_rate = null,
        public ?int $retake_discount_id = null,
        public ?string $student_code = null,
        public ?string $student_name = null,
        public ?string $student_status = null,
        public ?int $campus_id = null,
        public ?int $student_total_levels = null,
        public ?string $semester_name = null,
        public ?string $synced_at = null,
    ) {}
}
