<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

/**
 * Narrow read used only by StudentRegistry's soft-delete guard. Consumers
 * outside Finance go through this contract instead of reaching into
 * BillingAccount or the raw SettlementPositionReader directly. The row lock
 * needed to make the guard's check-then-act safe lives in the Finance-side
 * implementation, not the caller.
 */
interface StudentDeletionSettlementSnapshotReader
{
    /** @return array{valid: bool, remaining: float|null, unapplied: float|null} */
    public function guardSnapshotFor(int $studentId): array;
}
