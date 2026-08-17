<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry\DTO;

final readonly class AdmittedStudentIdentity
{
    public function __construct(
        public string $studentCode,
        public int $accountId,
        public string $fullName,
        public string $email,
        public int $campusId,
        public int $programId,
        public int $curriculumVersionId,
        public ?int $specializationId,
        public int $intakeSemesterId,
        public string $admissionDate,
        public ?string $expectedGraduationDate,
        public ?string $phone,
        public ?string $dateOfBirth,
        public ?string $gender,
        public string $nationality,
        public ?string $nationalId,
        public ?string $address,
        public ?string $emergencyContactName,
        public ?string $emergencyContactPhone,
        public ?string $emergencyContactRelationship,
        public ?string $highSchoolName,
        public ?string $admissionNotes,
        // Cohort number (khóa — K1, K2, …) from the CRM mapping screen's
        // intake config; null when the admission round has not declared one.
        public ?int $cohort = null,
    ) {}
}
