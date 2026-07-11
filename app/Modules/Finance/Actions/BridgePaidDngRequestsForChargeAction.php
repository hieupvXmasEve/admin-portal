<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BridgePaidDngRequestsForChargeAction
{
    public const PAID_DNG_STATUSES = [
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    public function __construct(
        private readonly DngPaymentService $dngPaymentService,
    ) {}

    /**
     * @return Collection<int, Payment>
     */
    public function handle(FinanceCharge|int $charge): Collection
    {
        $chargeId = $charge instanceof FinanceCharge ? (int) $charge->id : $charge;

        $bridged = $this->paidRequestsForCharge($chargeId)
            ->map(function (DngPaymentRequest $request) use ($chargeId): ?Payment {
                $payment = $this->dngPaymentService->bridgeToPayment($request);

                if ($payment !== null) {
                    Log::info('Paid DNG request bridged before source cancellation', [
                        'finance_charge_id' => $chargeId,
                        'dng_payment_request_id' => $request->id,
                        'payment_id' => $payment->id,
                        'dng_status' => $request->status,
                    ]);
                }

                return $payment;
            })
            ->filter()
            ->values();

        if ($bridged->isNotEmpty()) {
            // Avoid re-entrancy when ProcessFinanceCancellation is already bridging mid-void.
            // Resume only when there is completed unpaid-disposition work to upgrade, or
            // requested/review ops that need re-entry — always safe afterCommit job.
            app(ResumeFinanceCancellationOnPaidEvidenceAction::class)->handle($chargeId);
        }

        return $bridged;
    }

    public function hasPaidDngForCharge(?FinanceCharge $charge): bool
    {
        if ($charge === null || $charge->status !== FinanceCharge::STATUS_ACTIVE) {
            return false;
        }

        return $this->linkedPaidRequestIds((int) $charge->id)->isNotEmpty();
    }

    /**
     * @return Collection<int, DngPaymentRequest>
     */
    public function paidRequestsForCharge(int $chargeId): Collection
    {
        $requestIds = $this->linkedPaidRequestIds($chargeId);

        if ($requestIds->isEmpty()) {
            return collect();
        }

        return DngPaymentRequest::query()
            ->whereIn('id', $requestIds)
            ->whereIn('status', self::PAID_DNG_STATUSES)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function linkedPaidRequestIds(int $chargeId): Collection
    {
        $directIds = DngPaymentRequest::query()
            ->where('finance_charge_id', $chargeId)
            ->whereIn('status', self::PAID_DNG_STATUSES)
            ->pluck('id');

        $pivotIds = DngPaymentRequestCharge::query()
            ->where('finance_charge_id', $chargeId)
            ->whereHas(
                'dngPaymentRequest',
                fn ($query) => $query->whereIn('status', self::PAID_DNG_STATUSES)
            )
            ->pluck('dng_payment_request_id');

        return $directIds
            ->merge($pivotIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }
}
