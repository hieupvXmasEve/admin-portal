<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

interface AiFinanceStudentProfileReader
{
    /**
     * @return array<string, mixed>
     */
    public function financeSummary(int $studentId): array;
}
