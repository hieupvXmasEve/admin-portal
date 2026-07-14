<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;

final class GetStudentBalanceQuery
{
    public function __construct(
        private readonly StudentFinanceSettlementPositionReader $positionReader,
    ) {}

    /**
     * Legacy-shaped adapter for internal callers. Its amounts are deliberately
     * copied from the Finance-owned Settlement Position summary so Academic,
     * AI, and older presentation code cannot re-create a second balance rule.
     *
     * @return array{total_charges:float|null,total_credits:float|null,net_charges:float|null,total_paid:float|null,balance:float|null,unapplied_credit:float|null,applied_credit:float|null,status:string,valid:bool,issues:list<array{code:string,blocking:bool,evidence:array<string,int|string>}>}
     */
    public function handle(int $studentId, ?int $semesterId = null): array
    {
        $position = $this->positionReader->current($studentId, $semesterId);
        $valid = (bool) $position['valid'];

        return [
            'total_charges' => $valid ? $position['gross'] : null,
            'total_credits' => $valid ? $position['discount'] : null,
            'net_charges' => $valid ? $position['net_due'] : null,
            'total_paid' => $valid ? $position['cash_applied'] : null,
            'balance' => $valid ? $position['remaining_collectible'] : null,
            // Retained key: this is unapplied cash, not a reduction ledger.
            'unapplied_credit' => $valid ? $position['unapplied_cash'] : null,
            'applied_credit' => $valid ? $position['credit_applied'] : null,
            'status' => (string) $position['status'],
            'valid' => $valid,
            'issues' => $position['issues'],
        ];
    }
}
