<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class AcademicExamResitDueData
{
    public const STATUS_APPROVED = 'approved';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_CANCELLED = 'cancelled';

    public const HQ_FEE_PENDING = 'hq_fee_pending';

    public const HQ_FEE_CHARGE_CREATED = 'charge_created';

    public const HQ_FEE_PAID = 'paid';

    public const HQ_FEE_CANCELLED = 'cancelled';

    public function __construct(
        public int $id,
        public int $student_id,
        public int $campus_id,
        public int $semester_id,
        public string $status,
        public string $hq_fee_status,
        public ?float $amount = null,
        public ?string $unpaid_allowed_reason = null,
        public ?int $late_payment_grace_days_snapshot = null,
        public ?string $payment_deadline = null,
        public ?string $last_reminded_at = null,
        public ?string $student_code = null,
        public ?string $student_name = null,
        public ?string $student_email = null,
        public ?string $student_status = null,
        public ?string $student_status_label = null,
        public ?string $student_status_color = null,
        public ?string $unit_code = null,
        public ?string $unit_name = null,
        public ?string $exam_date = null,
        public ?string $exam_start_time = null,
        public ?string $exam_end_time = null,
        public ?string $room_name = null,
        public ?string $room_code = null,
    ) {}
}
