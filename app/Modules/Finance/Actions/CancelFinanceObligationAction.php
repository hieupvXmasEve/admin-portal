<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Finance\DTO\FinanceObligationCancellationData;
use App\Shared\Contracts\Finance\DTO\FinanceObligationCancellationResult;
use App\Shared\Contracts\Finance\FinanceObligationCancellationContract;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelFinanceObligationAction implements FinanceObligationCancellationContract
{
    public function __construct(
        private readonly BridgePaidDngRequestsForChargeAction $bridgePaidDngRequestsForChargeAction,
        private readonly VoidFinanceChargeAction $voidFinanceChargeAction,
    ) {}

    public function cancel(FinanceObligationCancellationData $data): FinanceObligationCancellationResult
    {
        return DB::transaction(function () use ($data): FinanceObligationCancellationResult {
            $obligation = $this->findObligation($data);
            $charge = $this->findCharge($obligation);

            $hasPaidDng = $this->bridgePaidDngRequestsForChargeAction->hasPaidDngForCharge($charge);
            if ($hasPaidDng && $charge !== null && ! $charge->is_fully_paid) {
                $this->bridgePaidDngRequestsForChargeAction->handle($charge);
                $charge = $charge->fresh();
                $this->assertPaidDngCoveredCharge($charge);
            }

            $isPaid = ($charge !== null && $charge->status === FinanceCharge::STATUS_ACTIVE && $charge->is_fully_paid)
                || $hasPaidDng;
            $hasUnpaidCharge = $charge !== null
                && $charge->status === FinanceCharge::STATUS_ACTIVE
                && ! $charge->is_fully_paid
                && ! $isPaid;

            if ($isPaid) {
                $this->assertNoRefundAcknowledged($data);
                $this->voidCharge($charge, $data->paid_no_refund_void_reason, $data->user_id, false);
                $this->markObligationVoided($obligation);

                return new FinanceObligationCancellationResult(
                    fee_disposition: FinanceObligationCancellationResult::FEE_DISPOSITION_KEPT_PAID_NO_REFUND,
                    is_paid: true,
                    had_unpaid_charge: false,
                    finance_charge_id: $charge?->id,
                );
            }

            if ($hasUnpaidCharge) {
                $this->assertUnpaidCancellationConfirmed($data);
                $this->cancelAwaitingDngRequestsForCharge((int) $charge->id);
                $this->voidCharge($charge, $data->unpaid_void_reason, $data->user_id);
                $this->markObligationVoided($obligation);

                return new FinanceObligationCancellationResult(
                    fee_disposition: FinanceObligationCancellationResult::FEE_DISPOSITION_VOIDED_UNPAID_CHARGE,
                    is_paid: false,
                    had_unpaid_charge: true,
                    finance_charge_id: $charge->id,
                );
            }

            if ($obligation instanceof FinanceObligation) {
                $obligation->update(['lifecycle_status' => FinanceObligation::STATUS_CANCELLED]);
            }

            return new FinanceObligationCancellationResult(
                fee_disposition: FinanceObligationCancellationResult::FEE_DISPOSITION_NO_CHARGE,
                is_paid: false,
                had_unpaid_charge: false,
                finance_charge_id: $charge?->id,
            );
        });
    }

    private function findObligation(FinanceObligationCancellationData $data): ?FinanceObligation
    {
        return FinanceObligation::query()
            ->where('source_system', $data->source_system)
            ->where('source_kind', $data->source_kind)
            ->where('source_ref', $data->source_ref)
            ->where('obligation_type', $data->obligation_type)
            ->lockForUpdate()
            ->first();
    }

    private function findCharge(?FinanceObligation $obligation): ?FinanceCharge
    {
        return $obligation?->financeCharge()
            ->lockForUpdate()
            ->first();
    }

    private function assertPaidDngCoveredCharge(?FinanceCharge $charge): void
    {
        if ($charge === null || $charge->status !== FinanceCharge::STATUS_ACTIVE || ! $charge->is_fully_paid) {
            throw new RuntimeException(
                'DNG đã ghi nhận thanh toán nhưng chưa thể phân bổ đủ vào khoản phí nội bộ. Vui lòng kiểm tra liên kết DNG/charge trước khi hủy.'
            );
        }
    }

    private function assertNoRefundAcknowledged(FinanceObligationCancellationData $data): void
    {
        if ($data->require_paid_no_refund_acknowledgement && ! $data->acknowledge_no_refund) {
            throw new RuntimeException(
                'Khoản phí đã thanh toán. Vui lòng xác nhận hủy nhưng giữ nguyên phí đã thu và không tạo hoàn phí.'
            );
        }
    }

    private function assertUnpaidCancellationConfirmed(FinanceObligationCancellationData $data): void
    {
        if (! $data->require_unpaid_confirmation) {
            return;
        }

        if ($data->unpaid_confirmation === null || $data->unpaid_confirmation !== $data->expected_unpaid_confirmation) {
            throw new RuntimeException('Vui lòng xác nhận hủy khoản phí đang chờ thu trước khi hủy nguồn.');
        }
    }

    private function voidCharge(?FinanceCharge $charge, string $reason, ?int $userId, bool $autoReallocate = true): void
    {
        if ($charge === null || $charge->status !== FinanceCharge::STATUS_ACTIVE) {
            return;
        }

        if ($autoReallocate) {
            $this->voidFinanceChargeAction->handle($charge->id, $reason, $userId);

            return;
        }

        $this->voidFinanceChargeAction->handle($charge->id, $reason, $userId, false);
    }

    private function markObligationVoided(?FinanceObligation $obligation): void
    {
        if (! $obligation instanceof FinanceObligation) {
            return;
        }

        $obligation->update(['lifecycle_status' => FinanceObligation::STATUS_VOIDED]);
    }

    private function cancelAwaitingDngRequestsForCharge(int $chargeId): int
    {
        $pivotIds = DngPaymentRequestCharge::query()
            ->where('finance_charge_id', $chargeId)
            ->whereHas('dngPaymentRequest', fn ($query) => $query->awaitingPayment())
            ->pluck('dng_payment_request_id');

        $requestIds = $pivotIds
            ->unique()
            ->values();

        if ($requestIds->isEmpty()) {
            return 0;
        }

        return DngPaymentRequest::query()
            ->whereIn('id', $requestIds)
            ->update([
                'status' => DngPaymentRequest::STATUS_CANCELLED,
                'active_slot_key' => null,
            ]);
    }
}
