<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\TranscriptEntryGpaData;

interface TranscriptEntryGpaReader
{
    /** @return list<TranscriptEntryGpaData> */
    public function forSemester(int $studentId, int|string $semesterId): array;

    /** @return list<TranscriptEntryGpaData> */
    public function throughSemester(int $studentId, int|string|null $semesterId): array;
}
