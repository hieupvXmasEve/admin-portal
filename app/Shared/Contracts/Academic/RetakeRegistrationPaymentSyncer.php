<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface RetakeRegistrationPaymentSyncer
{
    /**
     * @return array{checked:int,eligible:int,synced:int,waiting_for_class:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForStudent(int $studentId, bool $dryRun = false): array;

    /**
     * @param  array<int>  $chargeIds
     * @return array{checked:int,eligible:int,synced:int,waiting_for_class:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForChargeIds(array $chargeIds, bool $dryRun = false): array;
}
