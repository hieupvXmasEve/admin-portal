<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry\DTO;

final readonly class GuardianRelationship
{
    public function __construct(
        public int $id,
        public int $studentId,
        public ?int $sourceApplicationGuardianId,
        public string $fullName,
        public ?string $relationshipType,
        public ?string $phone,
        public ?string $email,
        public ?string $occupation,
        public ?string $address,
        public bool $isPrimary,
    ) {}

    /** @return array<string, int|string|bool|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->studentId,
            'full_name' => $this->fullName,
            'relationship_type' => $this->relationshipType,
            'phone' => $this->phone,
            'email' => $this->email,
            'occupation' => $this->occupation,
            'address' => $this->address,
            'is_primary' => $this->isPrimary,
        ];
    }
}
