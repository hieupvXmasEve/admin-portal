<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class ProgramEnrollmentSummary
{
    public function __construct(
        public ?int $enrollmentId,
        public ?int $programId,
        public ?string $programName,
        public ?string $programCode,
        public ?int $curriculumVersionId,
        public ?string $curriculumVersionCode,
        public ?int $specializationId,
        public ?string $specializationName,
        public ?string $specializationCode,
        public ?int $intakeSemesterId,
        public ?string $intakeSemesterCode,
        public ?string $intakeSemesterName,
        public ?string $intakeSemesterStartDate,
        public ?int $intakeMajorSemesterId,
        public ?string $intakeMajorSemesterCode,
        public ?string $intakeMajorSemesterName,
        public string $enrollmentStatus,
        public ?string $studyStage,
    ) {}

    public function legacyCompatibleStatus(): string
    {
        return $this->enrollmentStatus === 'active'
            ? $this->studyStage ?? $this->enrollmentStatus
            : $this->enrollmentStatus;
    }

    /**
     * @return array{id: int, name: string|null, code: string|null}|null
     */
    public function programPayload(): ?array
    {
        if ($this->programId === null) {
            return null;
        }

        return [
            'id' => $this->programId,
            'name' => $this->programName,
            'code' => $this->programCode,
        ];
    }

    /**
     * @return array{id: int, name: string|null, code: string|null}|null
     */
    public function specializationPayload(): ?array
    {
        if ($this->specializationId === null) {
            return null;
        }

        return [
            'id' => $this->specializationId,
            'name' => $this->specializationName,
            'code' => $this->specializationCode,
        ];
    }

    /**
     * @return array{id: int, code: string|null, name: string|null}|null
     */
    public function intakeSemesterPayload(): ?array
    {
        if ($this->intakeSemesterId === null) {
            return null;
        }

        return [
            'id' => $this->intakeSemesterId,
            'code' => $this->intakeSemesterCode,
            'name' => $this->intakeSemesterName,
        ];
    }

    /**
     * @return array{code: string|null, name: string|null}|null
     */
    public function intakeMajorSemesterPayload(): ?array
    {
        if ($this->intakeMajorSemesterId === null) {
            return null;
        }

        return [
            'code' => $this->intakeMajorSemesterCode,
            'name' => $this->intakeMajorSemesterName,
        ];
    }
}
