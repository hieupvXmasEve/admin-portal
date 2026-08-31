<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Models\ExamResitAttempt;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use App\Shared\Contracts\Finance\DTO\ObligationSettlementResult;
use Illuminate\Validation\ValidationException;

/**
 * Live-ledger payment gate for exam-resit schedule and complete.
 * hq_fee_status is display-only; settlement truth is Finance.
 */
final class ResitFeeGate
{
    public function __construct(
        private readonly AcademicObligationSettlement $settlement,
    ) {}

    public function evaluate(ExamResitAttempt $attempt): ObligationSettlementResult
    {
        return $this->settlement->forExamResit($attempt);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> Extra attributes to persist when an unpaid sitting is allowed.
     */
    public function assertCanProceed(
        ExamResitAttempt $attempt,
        array $data,
        string $unpaidBlockedMessage,
        string $missingReasonMessage,
    ): array {
        if ($this->evaluate($attempt)->isSettled()) {
            return [];
        }

        if (! (bool) $attempt->allow_unpaid_sitting_snapshot) {
            throw ValidationException::withMessages([
                'payment' => [$unpaidBlockedMessage],
            ]);
        }

        $reason = trim((string) ($data['unpaid_sitting_reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'unpaid_sitting_reason' => [$missingReasonMessage],
            ]);
        }

        return [
            'unpaid_allowed_reason' => $reason,
            'unpaid_allowed_by_user_id' => auth()->id(),
            'unpaid_allowed_at' => now(),
        ];
    }
}
