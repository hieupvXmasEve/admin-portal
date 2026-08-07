<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

/**
 * Whether a student already has an active tuition_term charge for a
 * semester. Academic (scholarship-adjustment candidate identification)
 * consumes this through the Shared contract only — never a Finance model
 * or table directly (ADR-0026 boundary).
 */
interface TuitionChargeExistenceReader
{
    /**
     * @param  int[]  $studentIds
     * @return array<int,bool> studentId => hasActiveTuitionTermCharge(semesterId); every input id is present
     */
    public function tuitionTermChargedByStudent(array $studentIds, int $semesterId): array;
}
