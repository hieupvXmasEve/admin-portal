<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

interface StudentPortalFinanceSummaryReader
{
    /** @return array<string, mixed> */
    public function handle(int $studentId): array;

    /** @return array<string, mixed>|null */
    public function semesterFor(int $studentId, int $semesterId): ?array;
}
