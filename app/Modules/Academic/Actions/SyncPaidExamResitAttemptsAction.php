<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Derive exam-resit (thi lại) HQ paid state from canonical Finance evidence.
 *
 * An attempt is considered paid only when its linked FinanceCharge is active and
 * fully paid (FinanceCharge::is_fully_paid, computed from payment_applications via
 * SettlementService). Payment is never asserted from a manual flag.
 *
 * @see SyncPaidRetakeRegistrationsAction cho luồng học lại (HL) tương đương.
 */
class SyncPaidExamResitAttemptsAction implements ExamResitAttemptPaymentSyncer
{
    /**
     * @return array{checked:int,eligible:int,synced:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runAll(bool $dryRun = false): array
    {
        return $this->runQuery(ExamResitAttempt::query(), $dryRun);
    }

    /**
     * @return array{checked:int,eligible:int,synced:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForStudent(int $studentId, bool $dryRun = false): array
    {
        return $this->runQuery(
            ExamResitAttempt::query()->where('student_id', $studentId),
            $dryRun,
        );
    }

    /**
     * @return array{checked:int,eligible:int,synced:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForAttempt(int $attemptId, bool $dryRun = false): array
    {
        return $this->runQuery(
            ExamResitAttempt::query()->whereKey($attemptId),
            $dryRun,
        );
    }

    /**
     * @param  array<int>  $chargeIds
     * @return array{checked:int,eligible:int,synced:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    public function runForChargeIds(array $chargeIds, bool $dryRun = false): array
    {
        $chargeIds = array_values(array_unique(array_filter(array_map('intval', $chargeIds))));

        if ($chargeIds === []) {
            return $this->emptyResult();
        }

        return $this->runQuery(
            ExamResitAttempt::query()->whereIn('finance_charge_id', $chargeIds),
            $dryRun,
        );
    }

    /**
     * @return array{checked:int,eligible:int,synced:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    private function runQuery(Builder $query, bool $dryRun): array
    {
        $result = $this->emptyResult();

        $attempts = $query
            ->where('hq_fee_status', ExamResitAttempt::HQ_FEE_CHARGE_CREATED)
            ->whereNotNull('finance_charge_id')
            ->with(['student:id,student_id,full_name', 'financeCharge'])
            ->orderBy('id')
            ->get();

        foreach ($attempts as $attempt) {
            $result['checked']++;

            $charge = $attempt->financeCharge;
            if (! $charge || $charge->status !== FinanceCharge::STATUS_ACTIVE || ! $charge->is_fully_paid) {
                $result['skipped']++;

                continue;
            }

            $result['eligible']++;
            $detail = [
                'attempt_id' => $attempt->id,
                'student_id' => $attempt->student?->student_id,
                'finance_charge_id' => $charge->id,
                'paid_amount' => $charge->paid_amount,
                'balance' => $charge->balance,
            ];

            if ($dryRun) {
                $result['details'][] = $detail + ['status' => 'eligible'];

                continue;
            }

            try {
                $attempt->transitionToPaid();
                $result['synced']++;
                $result['details'][] = $detail + ['status' => ExamResitAttempt::HQ_FEE_PAID];
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['details'][] = $detail + [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];

                Log::warning('Failed to sync paid exam resit attempt', [
                    'attempt_id' => $attempt->id,
                    'finance_charge_id' => $charge->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * @return array{checked:int,eligible:int,synced:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    private function emptyResult(): array
    {
        return [
            'checked' => 0,
            'eligible' => 0,
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];
    }
}
