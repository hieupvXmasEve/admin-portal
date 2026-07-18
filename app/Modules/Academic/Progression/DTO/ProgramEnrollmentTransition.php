<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\DTO;

final readonly class ProgramEnrollmentTransition
{
    public function __construct(
        public string $previousStatus,
        public string $newStatus,
        public ?string $previousStudyStage,
        public ?string $newStudyStage,
        public bool $changed,
    ) {}
}
