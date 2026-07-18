<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class TranscriptEntryGpaData
{
    public function __construct(
        public float $finalPercentage,
        public string $finalLetterGrade,
        public float $creditPoints,
        public float $creditPointsEarned,
        public float $qualityPoints,
        public bool $isPassed,
    ) {}
}
