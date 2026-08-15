<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Engagement\DTO;

final readonly class CourseSurveyTarget
{
    public function __construct(
        public int $id,
        public int $formId,
        public string $status,
        public string $startAt,
        public ?string $endAt,
        public ?string $formTitle,
        public ?string $formCode,
        public int $totalAssignments,
        public int $completedAssignments,
    ) {}
}
