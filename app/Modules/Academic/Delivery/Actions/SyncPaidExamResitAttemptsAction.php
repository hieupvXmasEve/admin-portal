<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ExamResitAttempt;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Derive exam-resit (thi lại) HQ paid state from Finance settlement (source triple).
 *
 * @see \App\Modules\Academic\Actions\SyncPaidRetakeRegistrationsAction for the retake equivalent.
 */
class SyncPaidExamResitAttemptsAction implements ExamResitAttemptPaymentSyncer
{
    public function __construct(
        private readonly AcademicObligationSettlement $obligationSettlement,
    ) {}

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
     * @return array{checked:int,eligible:int,synced:int,skipped:int,failed:int,details:array<int,array<string,mixed>>}
     */
    private function runQuery(Builder $query, bool $dryRun): array
    {
        $result = $this->emptyResult();

        $attempts = $query
            ->where('hq_fee_status', ExamResitAttempt::HQ_FEE_CHARGE_CREATED)
            ->with(['student:id,student_id,full_name'])
            ->orderBy('id')
            ->get();

        foreach ($attempts as $attempt) {
            $result['checked']++;

            $settlement = $this->obligationSettlement->forExamResit($attempt);
            if (! $settlement->isSettled()) {
                $result['skipped']++;

                continue;
            }

            $result['eligible']++;
            $detail = [
                'attempt_id' => $attempt->id,
                'student_id' => $attempt->student?->student_id,
                'finance_obligation_id' => $settlement->finance_obligation_id,
                'paid_amount' => $settlement->paid,
                'outstanding' => $settlement->outstanding,
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
                Log::error('SyncPaidExamResitAttempts: failed to mark paid', [
                    'attempt_id' => $attempt->id,
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
