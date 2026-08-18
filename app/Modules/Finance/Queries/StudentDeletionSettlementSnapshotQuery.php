<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;
use App\Shared\Contracts\Finance\StudentDeletionSettlementSnapshotReader;

/**
 * Implementation for App\Shared\Contracts\Finance\StudentDeletionSettlementSnapshotReader.
 */
final class StudentDeletionSettlementSnapshotQuery implements StudentDeletionSettlementSnapshotReader
{
    public function __construct(
        private readonly StudentFinanceSettlementPositionReader $settlementPositionReader,
    ) {}

    public function guardSnapshotFor(int $studentId): array
    {
        $billingAccount = BillingAccount::query()
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        if ($billingAccount === null) {
            return ['valid' => false, 'remaining' => null, 'unapplied' => null];
        }

        $position = $this->settlementPositionReader->current($studentId);

        return [
            'valid' => $position['valid'],
            'remaining' => $position['remaining_collectible'],
            'unapplied' => $position['unapplied_cash'],
        ];
    }
}
