<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\StudentScholarshipAward;
use App\Models\StudentWallet;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Shared\Contracts\Finance\HubStudentFinanceSummaryReader as HubStudentFinanceSummaryReaderContract;

/**
 * Finance-side implementation of the Hub's read-only finance summary contract.
 *
 * Every figure is delegated to the Finance module's own reads — balances come
 * from {@see GetStudentBalanceQuery}, the gold wallet and scholarship roster
 * are read directly. This keeps the cross-module join inside Finance (which
 * owns these tables) so the Academic Hub never reaches across the boundary.
 */
class HubStudentFinanceSummaryReader implements HubStudentFinanceSummaryReaderContract
{
    public function __construct(
        private readonly GetStudentBalanceQuery $balanceQuery,
    ) {}

    /**
     * @return array{
     *     fees: array{net_charges: float, total_paid: float, outstanding: float, unapplied_credit: float, status: string},
     *     gold: array{balance: int},
     *     scholarships: array<int, array{code: string, name: string|null, type: string|null, amount: float, awarded_at: string|null}>
     * }
     */
    public function summary(int $studentId): array
    {
        return [
            'fees' => $this->feeSummary($studentId),
            'gold' => $this->goldSummary($studentId),
            'scholarships' => $this->scholarshipSummary($studentId),
        ];
    }

    /**
     * @return array{net_charges: float, total_paid: float, outstanding: float, unapplied_credit: float, status: string}
     */
    private function feeSummary(int $studentId): array
    {
        $balance = $this->balanceQuery->handle($studentId);

        return [
            'net_charges' => (float) ($balance['net_charges'] ?? 0),
            'total_paid' => (float) ($balance['total_paid'] ?? 0),
            // Outstanding is the positive balance still owed; overpaid students show 0.
            'outstanding' => max(0.0, (float) ($balance['balance'] ?? 0)),
            'unapplied_credit' => (float) ($balance['unapplied_credit'] ?? 0),
            'status' => (string) ($balance['status'] ?? 'unknown'),
        ];
    }

    /**
     * @return array{balance: int}
     */
    private function goldSummary(int $studentId): array
    {
        // Read-only: never create a wallet here. Absent wallet reads as zero.
        $balance = StudentWallet::query()
            ->where('student_id', $studentId)
            ->value('balance');

        return [
            'balance' => (int) ($balance ?? 0),
        ];
    }

    /**
     * @return array<int, array{code: string, name: string|null, type: string|null, amount: float, awarded_at: string|null}>
     */
    private function scholarshipSummary(int $studentId): array
    {
        return StudentScholarshipAward::query()
            ->where('student_id', $studentId)
            ->with('scholarshipDefinition:code,name,type,amount')
            ->orderByDesc('awarded_at')
            ->get()
            ->map(fn (StudentScholarshipAward $award): array => [
                'code' => (string) $award->scholarship_code,
                'name' => $award->scholarshipDefinition?->name,
                'type' => $award->scholarshipDefinition?->type,
                'amount' => (float) ($award->scholarshipDefinition?->amount ?? 0),
                'awarded_at' => $award->awarded_at?->toDateString(),
            ])
            ->values()
            ->all();
    }
}
