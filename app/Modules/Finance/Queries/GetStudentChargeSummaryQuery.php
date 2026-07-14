<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries;

use App\Modules\Finance\Support\StudentFinanceSettlementPositionReader;

/**
 * Canonical summary for the student charges API.
 *
 * The field names are retained for portal compatibility. Every monetary value
 * originates from the current Settlement Position; reductions remain split so
 * the UI does not present credit application as cash paid.
 */
class GetStudentChargeSummaryQuery
{
    public function __construct(
        private readonly StudentFinanceSettlementPositionReader $positionReader,
    ) {}

    /** @return array<string, bool|float|null|string|array> */
    public function handle(int $studentId, ?int $semesterId = null): array
    {
        $position = $this->positionReader->current($studentId, $semesterId);

        if (! $position['valid']) {
            return [
                'total_charges' => null,
                'total_credits' => null,
                'net_amount' => null,
                'cash_amount' => null,
                'credit_amount' => null,
                'remaining_amount' => null,
                'settlement_position' => [
                    'valid' => false,
                    'state' => $position['settlement_state'],
                    'message' => StudentFinanceSettlementPositionReader::STUDENT_UNAVAILABLE_MESSAGE,
                    'issues' => $position['issues'],
                ],
            ];
        }

        return [
            'total_charges' => $position['gross'],
            'total_credits' => $position['discount'] + $position['credit_applied'],
            'net_amount' => $position['net_due'],
            'cash_amount' => $position['cash_applied'],
            'credit_amount' => $position['credit_applied'],
            'remaining_amount' => $position['remaining_collectible'],
            'settlement_position' => [
                'valid' => true,
                'state' => $position['settlement_state'],
                'message' => null,
                'issues' => [],
            ],
        ];
    }

    public function totalCharges(int $studentId, ?int $semesterId = null): float
    {
        return (float) ($this->handle($studentId, $semesterId)['total_charges'] ?? 0);
    }

    public function totalCredits(int $studentId, ?int $semesterId = null): float
    {
        return (float) ($this->handle($studentId, $semesterId)['total_credits'] ?? 0);
    }

    public function netAmount(int $studentId, ?int $semesterId = null): float
    {
        return (float) ($this->handle($studentId, $semesterId)['net_amount'] ?? 0);
    }
}
