<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry\DTO;

final readonly class StudentProfile
{
    public function __construct(
        public int $id,
        public string $studentCode,
        public ?int $userId,
        public string $fullName,
        public int $campusId,
        public ?string $phone,
        public ?string $dateOfBirth,
        public ?string $gender,
        public ?string $nationality,
        public ?string $ethnicity,
        public ?string $avatarUrl,
        public ?string $nationalId,
        public ?string $address,
        public ?string $currentAddressLine,
        public ?string $currentWard,
        public ?string $currentProvince,
        public ?string $currentCountry,
        public ?string $cccdAddress,
        public ?string $cccdAddressLine,
        public ?string $cccdWard,
        public ?string $cccdProvince,
        public ?string $cccdCountry,
        public ?string $email,
        public ?string $emergencyContactName,
        public ?string $emergencyContactPhone,
        public ?string $emergencyContactRelationship,
        public ?string $emergencyContactEmail,
        public ?string $emergencyContactName1,
        public ?string $emergencyContactEmail1,
        public ?string $emergencyContactPhone1,
        public ?string $emergencyContactRelationship1,
        public ?string $highSchoolName,
    ) {}
}
