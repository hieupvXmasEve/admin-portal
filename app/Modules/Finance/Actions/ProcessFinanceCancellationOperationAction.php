<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Services\DngReservationLifecycle;
use App\Modules\Finance\Jobs\DispatchFinanceCancellationCompletionJob;
use App\Modules\Finance\Models\FinanceCancellationCompletionOutbox;
use App\Modules\Finance\Models\FinanceCancellationOperation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\Finance\Enums\FinanceCancellationFeeDisposition;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Processes a durable Finance Cancellation Operation:
 * claim → cancel collection (provider outside settlement TX) → void payable
 * effects → create aggregate replacements from valid Settlement Position → outbox.
 *
 * Concurrency: only one worker claims STATUS_PROCESSING. Provider cancel attempts
 * are ledgered so stale reclaim never double-calls the provider. Late paid
 * evidence upgrades completed operations via append-only completion events.
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

    /** Stale processing claims may be reclaimed only for recovery — never re-calls in-flight provider. */
    public const PROCESSING_CLAIM_STALE_SECONDS = 300;

    public function __construct(
        private readonly CancelDngPaymentRequestAction $cancelDngPaymentRequestAction,
        private readonly BridgePaidDngRequestsForChargeAction $bridgePaidDngRequestsForChargeAction,
        private readonly VoidFinanceChargeAction $voidFinanceChargeAction,
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly SettlementMutationGuard $settlementMutationGuard,
        private readonly DngReservationLifecycle $dngReservationLifecycle,
    ) {}

    public function handle(int $operationId): FinanceCancellationOperation
    {
        $claimed = $this->claimForProcessing($operationId);
        if ($claimed === null) {
            $current = FinanceCancellationOperation::query()->findOrFail($operationId);

            if ($current->status === FinanceCancellationOperation::STATUS_COMPLETED) {
                return $this->reconcileLatePayment($current);
            }

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

            try {
                // Build replacement inside the same transaction, before voiding.
                // Invalid canonical evidence therefore commits only requires_review;
                // any later void failure rolls the replacement back atomically.
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
            } catch (RuntimeException $exception) {
                $locked->update([
                    'status' => FinanceCancellationOperation::STATUS_REQUIRES_REVIEW,
                    'processing_claimed_at' => null,
                    'result_payload' => array_merge($locked->result_payload ?? [], [
                        'review_reason' => $exception->getMessage(),
                    ]),
                ]);

                return $locked->fresh() ?? $locked;
            }

            $hasPaidDng = $lockedCharge !== null
                && $this->bridgePaidDngRequestsForChargeAction->hasPaidDngForCharge($lockedCharge);

            if ($hasPaidDng && $lockedCharge !== null) {
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
                'processing_claimed_at' => null,
                'result_payload' => array_merge($locked->result_payload ?? [], [
                    'is_paid' => $paid,
                    'finance_charge_id' => $lockedCharge?->id,
                    'fee_disposition' => $feeDisposition->value,
                    'had_unpaid_charge' => $feeDisposition === FinanceCancellationFeeDisposition::VoidedUnpaidCharge,
                ]),
            ]);

            $this->appendCompletionOutbox($locked, FinanceCancellationCompletionOutbox::EVENT_KIND_COMPLETED);

            return $locked->fresh() ?? $locked;
        });
    }

    /** @param array{operation_id: int} $data */
    public static function run(array $data): FinanceCancellationOperation
    {
        return app(self::class)->handle($data['operation_id']);
    }

    private function claimForProcessing(int $operationId): ?FinanceCancellationOperation
    {
        $staleBefore = now()->subSeconds(self::PROCESSING_CLAIM_STALE_SECONDS);

        $affected = FinanceCancellationOperation::query()
            ->whereKey($operationId)
            ->where(function ($query) use ($staleBefore): void {
                $query->whereIn('status', [
                    FinanceCancellationOperation::STATUS_REQUESTED,
                    FinanceCancellationOperation::STATUS_REQUIRES_REVIEW,
                ])->orWhere(function ($processing) use ($staleBefore): void {
                    $processing->where('status', FinanceCancellationOperation::STATUS_PROCESSING)
                        ->where(function ($claimed) use ($staleBefore): void {
                            $claimed->whereNull('processing_claimed_at')
                                ->orWhere('processing_claimed_at', '<', $staleBefore);
                        });
                });
            })
            ->update([
                'status' => FinanceCancellationOperation::STATUS_PROCESSING,
                'processing_claimed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($affected !== 1) {
            return null;
        }

        return FinanceCancellationOperation::query()->find($operationId);
    }

    private function reconcileLatePayment(FinanceCancellationOperation $operation): FinanceCancellationOperation
    {
        $payload = $operation->result_payload ?? [];
        $currentDisposition = $payload['fee_disposition'] ?? null;
        if ($currentDisposition === FinanceCancellationFeeDisposition::KeptPaidNoRefund->value) {
            return $operation;
        }

        $charge = $this->findCharge($operation);
        if (! $charge instanceof FinanceCharge) {
            return $operation;
        }

        $paidRequests = $this->bridgePaidDngRequestsForChargeAction
            ->paidRequestsForCharge((int) $charge->id);

        if ($paidRequests->isEmpty() && (
            $charge->status !== FinanceCharge::STATUS_ACTIVE || ! $charge->is_fully_paid
        )) {
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

            $this->appendCompletionOutbox(
                $locked,
                FinanceCancellationCompletionOutbox::EVENT_KIND_PAID_DISPOSITION_UPGRADE,
            );

            return $locked->fresh() ?? $locked;
        });
    }

    private function appendCompletionOutbox(FinanceCancellationOperation $operation, string $eventKind): void
    {
        $nextVersion = (int) FinanceCancellationCompletionOutbox::query()
            ->where('finance_cancellation_operation_id', $operation->id)
            ->max('event_version') + 1;

        $outbox = FinanceCancellationCompletionOutbox::query()->create([
            'finance_cancellation_operation_id' => $operation->id,
            'event_id' => sprintf(
                'finance-cancellation-operation-%s-%d-v%d',
                $eventKind,
                $operation->id,
                $nextVersion,
            ),
            'event_kind' => $eventKind,
            'event_version' => $nextVersion,
            'status' => FinanceCancellationCompletionOutbox::STATUS_PENDING,
        ]);

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

            $attempt = $this->providerAttempt($operation, (int) $request->id);

            // Stale reclaim: never re-call provider while a previous attempt is in-flight.
            if (in_array(($attempt['status'] ?? null), ['in_flight', 'unknown', 'failed'], true)) {
                $request->refresh();
                if (in_array($request->status, self::TERMINAL_COLLECTION_STATUSES, true)) {
                    $this->markProviderAttempt($operation, (int) $request->id, 'done');
                    $replacementPlans[] = [
                        'request_id' => (int) $request->id,
                        'voided_charge_id' => (int) $charge->id,
                    ];

                    continue;
                }

                $operation->update([
                    'status' => FinanceCancellationOperation::STATUS_REQUIRES_REVIEW,
                    'result_payload' => array_merge($operation->result_payload ?? [], [
                        'review_reason' => 'Stale processing reclaim refused to re-call provider for DNG #'.$request->id,
                    ]),
                ]);

                return false;
            }

            if (($attempt['status'] ?? null) === 'done') {
                $request->refresh();
                if (in_array($request->status, self::TERMINAL_COLLECTION_STATUSES, true)) {
                    $replacementPlans[] = [
                        'request_id' => (int) $request->id,
                        'voided_charge_id' => (int) $charge->id,
                    ];
                }

                continue;
            }

            try {
                $this->markProviderAttempt($operation, (int) $request->id, 'in_flight');
                $this->cancelDngPaymentRequestAction->run($request);
                $this->markProviderAttempt($operation, (int) $request->id, 'done');
            } catch (\Throwable $exception) {
                $request->refresh();
                $this->markProviderAttempt($operation, (int) $request->id, 'unknown');
                $operation->update([
                    'status' => FinanceCancellationOperation::STATUS_REQUIRES_REVIEW,
                    'result_payload' => array_merge($operation->result_payload ?? [], [
                        'review_reason' => 'Provider cancellation outcome is unknown for DNG #'.$request->id,
                        'provider_error' => mb_substr($exception->getMessage(), 0, 1000),
                    ]),
                ]);

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

    /** @return array{status?: string, at?: string} */
    private function providerAttempt(FinanceCancellationOperation $operation, int $requestId): array
    {
        $attempts = $operation->provider_attempts ?? [];

        return is_array($attempts[(string) $requestId] ?? null)
            ? $attempts[(string) $requestId]
            : [];
    }

    private function markProviderAttempt(FinanceCancellationOperation $operation, int $requestId, string $status): void
    {
        $attempts = $operation->provider_attempts ?? [];
        $attempts[(string) $requestId] = [
            'status' => $status,
            'at' => now()->toIso8601String(),
        ];
        $operation->forceFill(['provider_attempts' => $attempts])->save();
        $operation->refresh();
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
        $pivot = DngPaymentRequestCharge::query()
            ->where('finance_charge_id', $chargeId)
            ->whereHas('dngPaymentRequest', fn ($query) => $query->whereIn('status', $blockingStatuses))
            ->pluck('dng_payment_request_id');

        return DngPaymentRequest::query()
            ->whereIn('id', $pivot)
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
        return $this->findObligation($operation)?->financeCharge()->first();
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
            if ($canonical === null) {
                throw new RuntimeException(
                    'Settlement Position is invalid for charge #'.$target->finance_charge_id
                    .'; aggregate replacement requires review (no guessed amount).'
                );
            }
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

        $this->dngReservationLifecycle->createCancellationReplacement(
            $cancelledRequest,
            (int) $operation->id,
            $total,
            $replacementLinks,
        );
    }

    /**
     * Canonical remaining collectible for a charge.
     * Returns null when Settlement Position cannot be trusted (fail-closed).
     * Missing payable lines are non-canonical and therefore fail closed.
     */
    private function canonicalRemainingForCharge(int $chargeId): ?string
    {
        $lineIds = InvoiceLine::query()
            ->where('charge_id', $chargeId)
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($lineIds === []) {
            return null;
        }

        $position = count($lineIds) === 1
            ? $this->settlementPositionReader->forPayableLine($lineIds[0])
            : $this->settlementPositionReader->forPayableLines($lineIds);

        if (! $position->valid || $position->amounts === null) {
            return null;
        }

        $remaining = $position->amounts->remaining->amount;

        return bccomp($remaining, '0.00', 2) < 0 ? '0.00' : $remaining;
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
