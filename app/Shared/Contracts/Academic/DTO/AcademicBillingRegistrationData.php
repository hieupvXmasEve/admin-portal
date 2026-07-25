<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class AcademicBillingRegistrationData
{
    /**
     * @param  list<string>  $retake_source_refs
     */
    public function __construct(
        public int $id,
        public int $student_id,
        public ?int $semester_id,
        public int $course_offering_id,
        public string $registration_status,
        public bool $is_retake,
        public ?string $created_at,
        public ?int $offering_semester_id,
        public ?string $unit_name,
        public ?string $unit_code,
        public array $retake_source_refs,
    ) {}
}
