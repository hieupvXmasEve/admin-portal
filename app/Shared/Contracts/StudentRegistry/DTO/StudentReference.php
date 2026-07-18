<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry\DTO;

final readonly class StudentReference
{
    public function __construct(
        public int $id,
        public string $studentCode,
        public string $fullName,
        public int $campusId,
    ) {}

    /**
     * @return array{id: int, student_code: string, full_name: string, campus_id: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'student_code' => $this->studentCode,
            'full_name' => $this->fullName,
            'campus_id' => $this->campusId,
        ];
    }
}
