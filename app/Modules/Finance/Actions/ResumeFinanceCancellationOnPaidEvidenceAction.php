<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Jobs\ProcessFinanceCancellationOperationJob;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Finance\Enums\FinanceCancellationFeeDisposition;
use Illuminate\Support\Collection;

/**
 * Production trigger for PRD 7.5 late/mid-cancellation paid evidence.
 * Called after webhook/receipt/bridge captures verified cash for a charge that
 * may have an in-flight or completed cancellation operation.
 */
class ResumeFinanceCancellationOnPaidEvidenceAction
{
    /**
     * @return list<int> operation ids re-dispatched for processing
     */
    public function handle(int $financeChargeId): array
    {
        $operationIds = $this->matchingOperationIds($financeChargeId);
        foreach ($operationIds as $operationId) {
            ProcessFinanceCancellationOperationJob::dispatch($operationId)->afterCommit();
        }

        return $operationIds;
    }

    /** @param array{finance_charge_id: int} $data */
    public static function run(array $data): array
    {
        return app(self::class)->handle((int) $data['finance_charge_id']);
    }

    /**
     * @return list<int>
     */
    private function matchingOperationIds(int $financeChargeId): array
    {
        $obligation = FinanceCharge::query()
            ->whereKey($financeChargeId)
            ->first()?->financeObligation;
        if (! $obligation instanceof FinanceObligation) {
            return [];
        }

        /** @var Collection<int, FinanceCancellationOperation> $candidates */
        $candidates = FinanceCancellationOperation::query()
            ->where('source_system', $obligation->source_system)
            ->where('source_kind', $obligation->source_kind)
            ->where('source_ref', $obligation->source_ref)
            ->where('obligation_type', $obligation->obligation_type)
            ->whereIn('status', [
                FinanceCancellationOperation::STATUS_REQUESTED,
                FinanceCancellationOperation::STATUS_PROCESSING,
                FinanceCancellationOperation::STATUS_REQUIRES_REVIEW,
                FinanceCancellationOperation::STATUS_COMPLETED,
            ])
            ->orderBy('id')
            ->get();

        return $candidates
            ->filter(function (FinanceCancellationOperation $operation): bool {
                if ($operation->status !== FinanceCancellationOperation::STATUS_COMPLETED) {
                    return true;
                }

                $payload = $operation->result_payload ?? [];

                return ($payload['fee_disposition'] ?? null)
                    !== FinanceCancellationFeeDisposition::KeptPaidNoRefund->value;
            })
            ->map(fn (FinanceCancellationOperation $operation): int => (int) $operation->id)
            ->values()
            ->all();
    }
}
