<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Closure;

/** Resolves a held DNG provider outcome without changing settlement money. */
final class ResolveDngReservationOutcomeAction
{
    public function __construct(private readonly SettlementMutationGuard $settlementMutationGuard) {}

    /** @param array{request: DngPaymentRequest, outcome: string, evidence?: array<string, mixed>} $data */
    public static function run(array $data): DngPaymentRequest
    {
        return app(self::class)->handle($data['request'], $data['outcome'], $data['evidence'] ?? []);
    }

    /** @param array<string, mixed> $evidence */
    public function handle(DngPaymentRequest $request, string $outcome, array $evidence = []): DngPaymentRequest
    {
        $targetStatus = match ($outcome) {
            'pushed' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
            'restored_pending', 'failed' => DngPaymentRequest::STATUS_FAILED,
            'cancelled' => DngPaymentRequest::STATUS_CANCELLED,
            default => throw new \InvalidArgumentException("Unsupported DNG reservation reconciliation outcome '{$outcome}'."),
        };

        return $this->settlementMutationGuard->handleIfChanged(
            (int) $request->billing_account_id,
            function ($_billingAccount, Closure $markChanged) use ($request, $outcome, $targetStatus, $evidence): DngPaymentRequest {
                $lockedRequest = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
                if (! in_array($lockedRequest->status, [
                    DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
                    DngPaymentRequest::STATUS_NEEDS_REVIEW,
                ], true)) {
                    throw new \RuntimeException(
                        "DNG reservation #{$lockedRequest->id} is not held for reconciliation (status={$lockedRequest->status})."
                    );
                }

                if (! $lockedRequest->canTransitionTo($targetStatus)) {
                    throw new \RuntimeException(
                        "DNG reservation #{$lockedRequest->id} cannot resolve from {$lockedRequest->status} to {$targetStatus}."
                    );
                }

                $installmentIds = $lockedRequest->reservationTargets()
                    ->whereNotNull('finance_charge_installment_id')
                    ->pluck('finance_charge_installment_id')
                    ->map(static fn (int|string $id): int => (int) $id)
                    ->all();
                $installments = FinanceChargeInstallment::query()
                    ->whereIn('id', $installmentIds)
                    ->lockForUpdate()
                    ->get();

                $lockedRequest->transitionTo($targetStatus);
                $lockedRequest->update([
                    'review_evidence' => [
                        ...($lockedRequest->review_evidence ?? []),
                        'resolution' => $outcome,
                        'resolution_evidence' => $evidence,
                        'resolved_at' => now()->toIso8601String(),
                    ],
                    'error_message' => $outcome === 'pushed' ? null : $lockedRequest->error_message,
                ]);

                foreach ($installments as $installment) {
                    if ($installment->status === FinanceChargeInstallment::STATUS_PAID) {
                        continue;
                    }

                    $installment->update([
                        'dng_payment_request_id' => $lockedRequest->id,
                        'status' => $outcome === 'pushed'
                            ? FinanceChargeInstallment::STATUS_AWAITING_PAYMENT
                            : FinanceChargeInstallment::STATUS_PENDING,
                        'last_push_error' => $outcome === 'pushed'
                            ? null
                            : "DNG reservation #{$lockedRequest->id} resolved as {$outcome}; installment returned to pending.",
                    ]);
                }

                $markChanged();

                return $lockedRequest->fresh();
            },
        );
    }
}
