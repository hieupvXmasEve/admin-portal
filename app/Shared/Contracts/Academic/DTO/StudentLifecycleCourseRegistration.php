<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class StudentLifecycleCourseRegistration
{
    public function __construct(
        public int $id,
        public string $courseCode,
        public string $courseName,
        public string $semesterName,
        public ?int $semesterId,
        public string $registrationStatus,
    ) {}

    /**
     * @return array{id: int, course_code: string, course_name: string, semester_name: string, semester_id: int|null, registration_status: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'course_code' => $this->courseCode,
            'course_name' => $this->courseName,
            'semester_name' => $this->semesterName,
            'semester_id' => $this->semesterId,
            'registration_status' => $this->registrationStatus,
        ];
    }
}
