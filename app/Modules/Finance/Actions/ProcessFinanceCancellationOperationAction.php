<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Jobs\DispatchFinanceCancellationCompletionJob;
use App\Modules\Finance\Models\FinanceCancellationCompletionOutbox;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Services\SettlementService;
use App\Shared\Contracts\Finance\Enums\FinanceCancellationFeeDisposition;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Processes a durable Finance Cancellation Operation:
 * claim → cancel collection (provider outside settlement TX) → void payable
 * effects → create aggregate replacements from Settlement Position → outbox.
 *
 * Concurrency: only one worker claims STATUS_PROCESSING; others exit without
 * calling the provider. Late paid evidence after completion upgrades disposition.
 */
class ProcessFinanceCancellationOperationAction
{
    /** @deprecated Use FinanceCancellationFeeDisposition::NoCharge */
    public const FEE_DISPOSITION_NO_CHARGE = FinanceCancellationFeeDisposition::NoCharge->value;

    /** @deprecated Use FinanceCancellationFeeDisposition::VoidedUnpaidCharge */
    public const FEE_DISPOSITION_VOIDED_UNPAID_CHARGE = FinanceCancellationFeeDisposition::VoidedUnpaidCharge->value;

    /** @deprecated Use FinanceCancellationFeeDisposition::KeptPaidNoRefund */
    public const FEE_DISPOSITION_KEPT_PAID_NO_REFUND = FinanceCancellationFeeDisposition::KeptPaidNoRefund->value;

    private const TERMINAL_COLLECTION_STATUSES = [
        DngPaymentRequest::STATUS_CANCELLED,
        DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG,
    ];

    private const REVIEW_COLLECTION_STATUSES = [
        DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
        DngPaymentRequest::STATUS_NEEDS_REVIEW,
    ];

    /** Stale processing claims may be reclaimed after this window. */
    private const CLAIM_STALE_SECONDS = 300;

    public function __construct(
        private readonly CancelDngPaymentRequestAction $cancelDngPaymentRequestAction,
        private readonly BridgePaidDngRequestsForChargeAction $bridgePaidDngRequestsForChargeAction,
        private readonly VoidFinanceChargeAction $voidFinanceChargeAction,
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly SettlementService $settlementService,
    ) {}

    public function handle(int $operationId): FinanceCancellationOperation
    {
        $claimed = $this->claimForProcessing($operationId);
        if ($claimed === null) {
            $current = FinanceCancellationOperation::query()->findOrFail($operationId);

            if ($current->status === FinanceCancellationOperation::STATUS_COMPLETED) {
                return $this->reconcileLatePayment($current);
            }

            // Another worker owns STATUS_PROCESSING (or unclaimable state).
            return $current;
        }

        $charge = $this->findCharge($claimed);
        /** @var list<array{request_id: int, voided_charge_id: int}> $replacementPlans */
        $replacementPlans = [];

        if ($charge !== null && ! $this->cancelCollection($claimed, $charge, $replacementPlans)) {
            return $claimed->fresh() ?? $claimed;
        }

        return DB::transaction(function () use ($claimed, $charge, $replacementPlans): FinanceCancellationOperation {
            $locked = FinanceCancellationOperation::query()->lockForUpdate()->findOrFail($claimed->id);
            if ($locked->status === FinanceCancellationOperation::STATUS_COMPLETED) {
                return $this->reconcileLatePayment($locked);
            }

            $lockedCharge = $charge === null
                ? null
                : FinanceCharge::query()->lockForUpdate()->find($charge->id);

            $hasPaidDng = $lockedCharge !== null
                && $this->bridgePaidDngRequestsForChargeAction->hasPaidDngForCharge($lockedCharge);

            if ($hasPaidDng && $lockedCharge !== null) {
                // Bridge verified cash before any void releases applications.
                $this->bridgePaidDngRequestsForChargeAction->handle($lockedCharge);
                $lockedCharge->refresh();
            }

            $paid = $lockedCharge !== null && ($hasPaidDng || $lockedCharge->is_fully_paid);

            if ($lockedCharge !== null && $lockedCharge->status === FinanceCharge::STATUS_ACTIVE) {
                $this->voidFinanceChargeAction->handle(
                    $lockedCharge->id,
                    $paid ? $locked->paid_void_reason : $locked->unpaid_void_reason,
                    $locked->actor_user_id,
                    false,
                );
                $lockedCharge->refresh();
            }

            // Replacement only after void succeeds inside this transaction so a
            // void failure cannot leave an orphan replacement DNG.
            foreach ($replacementPlans as $plan) {
                $cancelledRequest = DngPaymentRequest::query()->find($plan['request_id']);
                if ($cancelledRequest instanceof DngPaymentRequest) {
                    $this->createReplacementForRemainingTargets(
                        $locked,
                        $cancelledRequest,
                        $plan['voided_charge_id'],
                    );
                }
            }

            $obligation = $this->findObligation($locked);
            if ($obligation !== null) {
                $obligation->update([
                    'lifecycle_status' => $lockedCharge === null
                        ? FinanceObligation::STATUS_CANCELLED
                        : FinanceObligation::STATUS_VOIDED,
                ]);
            }

            $feeDisposition = $this->resolveFeeDisposition($lockedCharge, $paid);
            $locked->update([
                'status' => FinanceCancellationOperation::STATUS_COMPLETED,
                'completed_at' => now(),
                'result_payload' => [
                    'is_paid' => $paid,
                    'finance_charge_id' => $lockedCharge?->id,
                    'fee_disposition' => $feeDisposition->value,
                    'had_unpaid_charge' => $feeDisposition === FinanceCancellationFeeDisposition::VoidedUnpaidCharge,
                ],
            ]);

            $this->queueCompletionOutbox($locked);

            return $locked->fresh() ?? $locked;
        });
    }

    /** @param array{operation_id: int} $data */
    public static function run(array $data): FinanceCancellationOperation
    {
        return app(self::class)->handle($data['operation_id']);
    }

    /**
     * Optimistic claim: only one concurrent worker transitions into processing.
     */
    private function claimForProcessing(int $operationId): ?FinanceCancellationOperation
    {
        $staleBefore = now()->subSeconds(self::CLAIM_STALE_SECONDS);

        $affected = FinanceCancellationOperation::query()
            ->whereKey($operationId)
            ->where(function ($query) use ($staleBefore): void {
                $query->whereIn('status', [
                    FinanceCancellationOperation::STATUS_REQUESTED,
                    FinanceCancellationOperation::STATUS_REQUIRES_REVIEW,
                ])->orWhere(function ($processing) use ($staleBefore): void {
                    $processing->where('status', FinanceCancellationOperation::STATUS_PROCESSING)
                        ->where('updated_at', '<', $staleBefore);
                });
            })
            ->update([
                'status' => FinanceCancellationOperation::STATUS_PROCESSING,
                'updated_at' => now(),
            ]);

        if ($affected !== 1) {
            return null;
        }

        return FinanceCancellationOperation::query()->find($operationId);
    }

    /**
     * PRD 7.5: cash confirmed after completion upgrades to paid disposition.
     */
    private function reconcileLatePayment(FinanceCancellationOperation $operation): FinanceCancellationOperation
    {
        $payload = $operation->result_payload ?? [];
        $currentDisposition = $payload['fee_disposition'] ?? null;
        if ($currentDisposition === FinanceCancellationFeeDisposition::KeptPaidNoRefund->value) {
            return $operation;
        }

        $chargeId = $payload['finance_charge_id']
            ?? $operation->source_payload['legacy_finance_charge_id']
            ?? null;
        if ($chargeId === null) {
            $chargeId = $this->findCharge($operation)?->id;
        }
        if ($chargeId === null) {
            return $operation;
        }

        $charge = FinanceCharge::query()->find((int) $chargeId);
        if (! $charge instanceof FinanceCharge) {
            return $operation;
        }

        // Paid DNG evidence is valid even after the charge was voided.
        $paidRequestIds = $this->bridgePaidDngRequestsForChargeAction
            ->paidRequestsForCharge((int) $charge->id);

        if ($paidRequestIds->isEmpty() && ! $charge->is_fully_paid) {
            return $operation;
        }

        return DB::transaction(function () use ($operation, $charge): FinanceCancellationOperation {
            $locked = FinanceCancellationOperation::query()->lockForUpdate()->findOrFail($operation->id);
            $payload = $locked->result_payload ?? [];
            if (($payload['fee_disposition'] ?? null) === FinanceCancellationFeeDisposition::KeptPaidNoRefund->value) {
                return $locked;
            }

            $this->bridgePaidDngRequestsForChargeAction->handle($charge);

            $locked->update([
                'result_payload' => array_merge($payload, [
                    'is_paid' => true,
                    'finance_charge_id' => $charge->id,
                    'fee_disposition' => FinanceCancellationFeeDisposition::KeptPaidNoRefund->value,
                    'had_unpaid_charge' => false,
                    'late_paid_disposition' => true,
                ]),
            ]);

            $this->queueCompletionOutbox($locked, redispatch: true);

            return $locked->fresh() ?? $locked;
        });
    }

    private function queueCompletionOutbox(FinanceCancellationOperation $operation, bool $redispatch = false): void
    {
        $outbox = FinanceCancellationCompletionOutbox::query()->firstOrCreate(
            ['finance_cancellation_operation_id' => $operation->id],
            [
                'event_id' => 'finance-cancellation-operation-completed-'.$operation->id,
                'status' => FinanceCancellationCompletionOutbox::STATUS_PENDING,
            ],
        );

        if ($redispatch && $outbox->status === FinanceCancellationCompletionOutbox::STATUS_DISPATCHED) {
            $outbox->update([
                'event_id' => 'finance-cancellation-operation-completed-'.$operation->id.'-paid-'.now()->timestamp,
                'status' => FinanceCancellationCompletionOutbox::STATUS_PENDING,
                'dispatched_at' => null,
            ]);
        }

        DispatchFinanceCancellationCompletionJob::dispatch($outbox->id)->afterCommit();
    }

    /**
     * @param  list<array{request_id: int, voided_charge_id: int}>  $replacementPlans
     */
    private function cancelCollection(
        FinanceCancellationOperation $operation,
        FinanceCharge $charge,
        array &$replacementPlans,
    ): bool {
        foreach ($this->awaitingRequests($charge->id) as $request) {
            if (in_array($request->status, self::REVIEW_COLLECTION_STATUSES, true)) {
                $operation->update(['status' => FinanceCancellationOperation::STATUS_REQUIRES_REVIEW]);

                return false;
            }

            try {
                // Provider calls occur outside this operation's settlement transaction.
                $this->cancelDngPaymentRequestAction->run($request);
            } catch (\Throwable) {
                $operation->update(['status' => FinanceCancellationOperation::STATUS_REQUIRES_REVIEW]);

                return false;
            }

            $request->refresh();
            if (in_array($request->status, self::REVIEW_COLLECTION_STATUSES, true)) {
                $operation->update(['status' => FinanceCancellationOperation::STATUS_REQUIRES_REVIEW]);

                return false;
            }

            if (! in_array($request->status, self::TERMINAL_COLLECTION_STATUSES, true)
                && ! in_array($request->status, BridgePaidDngRequestsForChargeAction::PAID_DNG_STATUSES, true)) {
                $operation->update(['status' => FinanceCancellationOperation::STATUS_REQUIRES_REVIEW]);

                return false;
            }

            if (in_array($request->status, self::TERMINAL_COLLECTION_STATUSES, true)) {
                $replacementPlans[] = [
                    'request_id' => (int) $request->id,
                    'voided_charge_id' => (int) $charge->id,
                ];
            }
        }

        return true;
    }

    /** @return Collection<int, DngPaymentRequest> */
    private function awaitingRequests(int $chargeId): Collection
    {
        $blockingStatuses = [
            DngPaymentRequest::STATUS_PENDING,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG,
            DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
            DngPaymentRequest::STATUS_NEEDS_REVIEW,
        ];
        $direct = DngPaymentRequest::query()
            ->whereIn('status', $blockingStatuses)
            ->where('finance_charge_id', $chargeId)
            ->pluck('id');
        $pivot = DngPaymentRequestCharge::query()
            ->where('finance_charge_id', $chargeId)
            ->whereHas('dngPaymentRequest', fn ($query) => $query->whereIn('status', $blockingStatuses))
            ->pluck('dng_payment_request_id');

        return DngPaymentRequest::query()
            ->whereIn('id', $direct->merge($pivot)->unique())
            ->orderBy('id')
            ->get();
    }

    private function findObligation(FinanceCancellationOperation $operation): ?FinanceObligation
    {
        return FinanceObligation::query()->where([
            'source_system' => $operation->source_system,
            'source_kind' => $operation->source_kind,
            'source_ref' => $operation->source_ref,
            'obligation_type' => $operation->obligation_type,
        ])->first();
    }

    private function findCharge(FinanceCancellationOperation $operation): ?FinanceCharge
    {
        $charge = $this->findObligation($operation)?->financeCharge()->first();
        if ($charge instanceof FinanceCharge) {
            return $charge;
        }

        $legacyChargeId = $operation->source_payload['legacy_finance_charge_id'] ?? null;

        return $legacyChargeId === null ? null : FinanceCharge::query()->find((int) $legacyChargeId);
    }

    private function createReplacementForRemainingTargets(
        FinanceCancellationOperation $operation,
        DngPaymentRequest $cancelledRequest,
        int $voidedChargeId,
    ): void {
        $targets = DngPaymentRequestCharge::query()
            ->where('dng_payment_request_id', $cancelledRequest->id)
            ->where('finance_charge_id', '!=', $voidedChargeId)
            ->whereHas('financeCharge', fn ($query) => $query->where('status', FinanceCharge::STATUS_ACTIVE))
            ->with('financeCharge.invoiceLines')
            ->get();

        if ($targets->isEmpty()) {
            return;
        }

        $replacementLinks = [];
        $total = '0.00';

        foreach ($targets as $target) {
            $canonical = $this->canonicalRemainingForCharge((int) $target->finance_charge_id);
            if (bccomp($canonical, '0.00', 2) <= 0) {
                continue;
            }

            $replacementLinks[] = [
                'finance_charge_id' => (int) $target->finance_charge_id,
                'amount' => $canonical,
                'finance_charge_installment_id' => $target->finance_charge_installment_id,
            ];
            $total = bcadd($total, $canonical, 2);
        }

        if ($replacementLinks === [] || bccomp($total, '0.00', 2) <= 0) {
            return;
        }

        $replacement = DngPaymentRequest::query()->firstOrCreate(
            ['item_id' => $cancelledRequest->item_id.'-replacement-'.$operation->id],
            [
                'student_id' => $cancelledRequest->student_id,
                'billing_account_id' => $cancelledRequest->billing_account_id,
                'campus_code' => $cancelledRequest->campus_code,
                'provider_rail' => $cancelledRequest->provider_rail,
                'student_code' => $cancelledRequest->student_code,
                'fee_type' => $cancelledRequest->fee_type,
                'description' => 'Replacement after cancellation of DNG #'.$cancelledRequest->id,
                'semester_id' => $cancelledRequest->semester_id,
                'due_date' => $cancelledRequest->due_date,
                'amount' => $total,
                'status' => DngPaymentRequest::STATUS_PENDING,
                'push_payload' => ['replacement_for_dng_payment_request_id' => $cancelledRequest->id],
            ],
        );

        // Keep amount aligned when firstOrCreate hit an existing row with stale amount.
        if ((string) $replacement->amount !== $total) {
            $replacement->update(['amount' => $total]);
        }

        foreach ($replacementLinks as $link) {
            DngPaymentRequestCharge::query()->updateOrCreate(
                [
                    'dng_payment_request_id' => $replacement->id,
                    'finance_charge_id' => $link['finance_charge_id'],
                ],
                [
                    'amount' => $link['amount'],
                    'finance_charge_installment_id' => $link['finance_charge_installment_id'],
                ],
            );
        }
    }

    /**
     * Remaining collectible for a charge from Settlement Position when lines
     * exist; otherwise falls back to gross charge amount (no line yet).
     */
    private function canonicalRemainingForCharge(int $chargeId): string
    {
        $lineIds = InvoiceLine::query()
            ->where('charge_id', $chargeId)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($lineIds !== []) {
            $position = count($lineIds) === 1
                ? $this->settlementPositionReader->forPayableLine($lineIds[0])
                : $this->settlementPositionReader->forPayableLines($lineIds);

            if ($position->amounts !== null) {
                $remaining = $position->amounts->remaining->amount;

                return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
            }

            // Settlement Position may be invalid (e.g. missing obligation currency)
            // while ledger cash/discount still define collectible remaining.
            $paid = $this->settlementService->getChargePaidAmount($chargeId);
            $discount = $this->settlementService->getChargeDiscountAmount($chargeId);
            $gross = (float) InvoiceLine::query()
                ->whereIn('id', $lineIds)
                ->sum('amount_snapshot');
            $remaining = max(0, round($gross - $discount - $paid, 2));

            return number_format($remaining, 2, '.', '');
        }

        $charge = FinanceCharge::query()->find($chargeId);
        if (! $charge instanceof FinanceCharge || $charge->status !== FinanceCharge::STATUS_ACTIVE) {
            return '0.00';
        }

        return number_format((float) $charge->amount, 2, '.', '');
    }

    private function resolveFeeDisposition(?FinanceCharge $charge, bool $paid): FinanceCancellationFeeDisposition
    {
        if ($paid) {
            return FinanceCancellationFeeDisposition::KeptPaidNoRefund;
        }

        if ($charge === null) {
            return FinanceCancellationFeeDisposition::NoCharge;
        }

        return FinanceCancellationFeeDisposition::VoidedUnpaidCharge;
    }
}
