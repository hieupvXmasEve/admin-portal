<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface ExamResitAttemptPaymentSyncer
{
    /**
     * @return array{checked:int,eligible:int,synced:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForStudent(int $studentId, bool $dryRun = false): array;
}
