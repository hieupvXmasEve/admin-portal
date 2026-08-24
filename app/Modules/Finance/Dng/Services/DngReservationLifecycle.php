<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Dng\Exceptions\DngReservationReplacementRequired;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Dng\Support\DngReservationTargetFingerprint;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Modules\Finance\Support\SettlementPosition\SettlementPosition;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Coordinates the durable DNG reservation lifecycle around an external provider call. */
final class DngReservationLifecycle
{
    private const PROVIDER_RAIL = 'dng';

    public function __construct(
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly DngCampusCodeResolver $campusCodeResolver,
        private readonly DngPaymentService $dngPaymentService,
        private readonly ?SettlementMutationGuard $settlementMutationGuard = null,
        private readonly ?StudentReferenceReader $studentReferences = null,
        private readonly ?CancelDngPaymentRequestAction $cancelDngPaymentRequestAction = null,
    ) {}

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details */
    public function reserveAndPush(int $studentId, string $feeType, array $details, ?array $invoiceLineIds = null, array $targetAmounts = [], array $installmentIdsByLine = []): DngPaymentRequest
    {
        return $this->push($studentId, $this->reserve($studentId, $feeType, $details, $invoiceLineIds, $targetAmounts, $installmentIdsByLine), $details);
    }

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details */
    public function reserve(int $studentId, string $feeType, array $details, ?array $requestedLineIds = null, array $targetAmounts = [], array $installmentIdsByLine = []): DngPaymentRequest
    {
        return $this->attemptReserve($studentId, $feeType, $details, $requestedLineIds, $targetAmounts, $installmentIdsByLine, false);
    }

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details */
    private function attemptReserve(int $studentId, string $feeType, array $details, ?array $requestedLineIds, array $targetAmounts, array $installmentIdsByLine, bool $isReplacementRetry): DngPaymentRequest
    {
        $chargeTypes = ListDngWorklistQuery::mapFeeTypeToChargeTypes($feeType);
        if ($chargeTypes === []) {
            throw new \InvalidArgumentException("DNG fee type {$feeType} has no supported Finance charge types.");
        }

        $student = $this->studentReference($studentId);
        $this->assertRequiredPayerProfile($student);
        $billingAccountId = (int) BillingAccount::query()->where('student_id', $studentId)->sole()->id;

        // Captured before entering the guard: tells us, once a replacement
        // signal unwinds it, whether *this* call opened the transaction
        // (safe to cancel now) or merely reused an ambient one owned by a
        // caller further up the stack (must propagate for that caller to
        // cancel once its own transaction has actually closed).
        $wasAlreadyGuarded = $this->guard()->isActive($billingAccountId);
        $transactionLevelBeforeGuard = DB::transactionLevel();

        try {
            return $this->guard()->handleIfChanged($billingAccountId, function (BillingAccount $billingAccount, Closure $markChanged) use ($student, $studentId, $feeType, $chargeTypes, $details, $requestedLineIds, $targetAmounts, $installmentIdsByLine): DngPaymentRequest {
                $campusCode = $this->campusCodeResolver->requireForCampusId($student->campusId);
                $slotKey = $this->slotKey((int) $billingAccount->id, $campusCode, $feeType);
                $existing = DngPaymentRequest::query()
                    ->holdingCollection()
                    ->where('billing_account_id', $billingAccount->id)
                    ->where('provider_rail', self::PROVIDER_RAIL)
                    ->where('fee_type', $feeType)
                    ->lockForUpdate()
                    ->first();
                if ($existing !== null && $existing->campus_code !== $campusCode) {
                    throw new \RuntimeException('An unresolved DNG request exists on another campus and must be reconciled before creating a replacement.');
                }

                $targetLineIds = InvoiceLine::query()
                    ->where('status', 'active')
                    ->whereHas('charge', function ($query) use ($studentId, $chargeTypes, $details): void {
                        $query->where('student_id', $studentId)
                            ->whereIn('charge_type', $chargeTypes)
                            ->where('semester_id', $details['semester_id'])
                            ->where('status', FinanceCharge::STATUS_ACTIVE);
                    })
                    ->when($requestedLineIds !== null, fn ($query) => $query->whereIn('id', $requestedLineIds))
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();
                if ($targetLineIds === []) {
                    throw new \RuntimeException('Settlement Position has no supported payable lines for this DNG fee type.');
                }

                $position = $this->settlementPositionReader->forPayableLines($targetLineIds);
                if (! $position->isValid() || $position->amounts === null || ! $position->amounts->remaining->isPositive()) {
                    throw new \RuntimeException('Settlement Position is invalid, held, or has no collectible amount.');
                }

                $targets = $this->collectibleTargets($position, $targetAmounts);
                if ($targets === []) {
                    throw new \RuntimeException('Settlement Position has no exact collectible targets.');
                }

                $targetLineIds = array_column($targets, 'invoice_line_id');
                InvoiceLine::query()->whereIn('id', $targetLineIds)->lockForUpdate()->get();
                $position = $this->settlementPositionReader->forPayableLines($targetLineIds);
                if (! $position->isValid() || $position->amounts === null || ! $position->amounts->remaining->isPositive()) {
                    throw new \RuntimeException('Settlement Position changed while reserving DNG targets.');
                }
                $targets = $this->collectibleTargets($position, $targetAmounts);
                $targetFingerprint = $this->fingerprint($targets);
                $amount = array_reduce($targets, fn (Money $total, array $target): Money => $total->add(Money::vnd($target['collectible'])), Money::zero());
                $sequence = DngPaymentRequest::query()
                    ->where('billing_account_id', $billingAccount->id)
                    ->where('provider_rail', self::PROVIDER_RAIL)
                    ->where('campus_code', $campusCode)
                    ->where('fee_type', $feeType)
                    ->lockForUpdate()
                    ->count() + 1;
                if ($existing !== null) {
                    if (! $this->isExactRetry(
                        $existing,
                        $student,
                        $billingAccount,
                        $campusCode,
                        $feeType,
                        $details,
                        $slotKey,
                        $targets,
                        $amount,
                        $targetFingerprint,
                        $installmentIdsByLine,
                        $sequence - 1,
                        ! in_array($existing->status, [
                            DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
                            DngPaymentRequest::STATUS_NEEDS_REVIEW,
                        ], true),
                    )) {
                        if ($this->eligibleForAutomaticReplacement($existing, $targets, $amount)) {
                            throw new DngReservationReplacementRequired((int) $existing->id);
                        }

                        $this->holdForReview(
                            $existing,
                            $targetFingerprint,
                            'An active DNG reservation no longer matches the requested payer, settlement targets, amount, version, or provider identity.',
                        );

                        return $existing->fresh();
                    }

                    return $existing;
                }
                $reservation = DngPaymentRequest::query()->create([
                    'student_id' => $student->id,
                    'billing_account_id' => $billingAccount->id,
                    'campus_code' => $campusCode,
                    'provider_rail' => self::PROVIDER_RAIL,
                    'student_code' => $student->studentCode,
                    'fee_type' => $feeType,
                    'description' => $details['description'],
                    'semester_id' => $details['semester_id'],
                    'due_date' => $details['due_date'],
                    'item_id' => $this->itemId((int) $billingAccount->id, $campusCode, $feeType, $sequence),
                    'active_slot_key' => $slotKey,
                    'amount' => $amount->amount,
                    'status' => DngPaymentRequest::STATUS_PENDING,
                    'captured_settlement_version' => (int) $billingAccount->settlement_version,
                    'target_fingerprint' => $targetFingerprint,
                    'reserved_at' => now(),
                ]);
                foreach ($targets as $target) {
                    DngPaymentRequestReservationTarget::query()->create([
                        'dng_payment_request_id' => $reservation->id,
                        'invoice_line_id' => $target['invoice_line_id'],
                        'captured_collectible' => $target['collectible'],
                        'target_identity' => $target['identity'],
                        'finance_charge_installment_id' => $installmentIdsByLine[$target['invoice_line_id']] ?? null,
                    ]);
                    DngPaymentRequestCharge::query()->create([
                        'dng_payment_request_id' => $reservation->id,
                        'finance_charge_id' => $target['finance_charge_id'],
                        'amount' => $target['collectible'],
                        'finance_charge_installment_id' => $installmentIdsByLine[$target['invoice_line_id']] ?? null,
                    ]);
                }
                $markChanged();

                return $reservation;
            });
        } catch (DngReservationReplacementRequired $signal) {
            if ($wasAlreadyGuarded) {
                // Not our transaction to close. Propagate so the caller that
                // actually owns the outermost guard scope for this billing
                // account can cancel once it has unwound.
                throw $signal;
            }

            if ($isReplacementRetry) {
                throw new \RuntimeException(
                    "DNG reservation replacement did not converge after cancelling request #{$signal->existingRequestId}; a second conflicting collection remains and needs manual reconciliation.",
                );
            }

            // The guard's transaction has already rolled back (nothing was
            // persisted) and unregistered itself by the time an exception
            // reaches here, so the provider cancel below must run with no
            // transaction or row lock held. Fail loud instead of silently
            // cancelling under a lock if that invariant somehow does not hold.
            if (DB::transactionLevel() !== $transactionLevelBeforeGuard) {
                throw new \RuntimeException('Refusing an automatic DNG cancellation while a database transaction is open.');
            }

            $existing = DngPaymentRequest::query()->findOrFail($signal->existingRequestId);
            $this->cancelAction()->run($existing, ['trigger' => 'automatic_replacement', 'actor_user_id' => auth()->id()]);

            return $this->attemptReserve($studentId, $feeType, $details, $requestedLineIds, $targetAmounts, $installmentIdsByLine, true);
        }
    }

    /**
     * Replace automatically only when every safety condition holds; see
     * plans/260818-2139-dng-push-over-collection-replacement/phase-01-start.md.
     *
     * The new targets must be a superset of the existing request's stored
     * targets (every invoice line it already covers, at no less than its
     * captured amount) with a strictly larger total. A same-total or
     * different-but-not-strictly-larger retry — including one whose only
     * "difference" from the stored row is settlement-version drift from the
     * push itself — must fall through to holdForReview instead, or an
     * unrelated live collection could be cancelled with nothing covering the
     * charge it protected.
     *
     * @param  list<array{invoice_line_id: int, finance_charge_id: int, collectible: string, identity: string}>  $newTargets
     */
    private function eligibleForAutomaticReplacement(DngPaymentRequest $existing, array $newTargets, Money $newAmount): bool
    {
        if (! config('finance.dng.auto_replace_stale_collection', false)) {
            return false;
        }

        if (! in_array($existing->status, [
            DngPaymentRequest::STATUS_PENDING,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        ], true)) {
            return false;
        }

        $existingTargets = $existing->reservationTargets()->get();
        if ($existingTargets->isEmpty()) {
            return false;
        }

        $newByLine = collect($newTargets)->keyBy('invoice_line_id');
        foreach ($existingTargets as $existingTarget) {
            $current = $newByLine->get((int) $existingTarget->invoice_line_id);
            if ($current === null) {
                return false;
            }

            if (Money::vnd((string) $existingTarget->captured_collectible)->isGreaterThan(Money::vnd($current['collectible']))) {
                return false;
            }
        }

        return $newAmount->isGreaterThan(Money::vnd((string) $existing->amount));
    }

    private function cancelAction(): CancelDngPaymentRequestAction
    {
        return $this->cancelDngPaymentRequestAction ?? app(CancelDngPaymentRequestAction::class);
    }

    /**
     * @param  list<array{finance_charge_id: int, amount: string, finance_charge_installment_id: int|null}>  $replacementLinks
     */
    public function createCancellationReplacement(
        DngPaymentRequest $cancelledRequest,
        int $operationId,
        string $total,
        array $replacementLinks,
    ): DngPaymentRequest {
        return $this->guard()->handle((int) $cancelledRequest->billing_account_id, function () use ($cancelledRequest, $operationId, $total, $replacementLinks): DngPaymentRequest {
            $replacement = DngPaymentRequest::query()->firstOrCreate(
                ['item_id' => $cancelledRequest->item_id.'-replacement-'.$operationId],
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

            return $replacement;
        });
    }

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details */
    public function push(int $studentId, DngPaymentRequest $reservation, array $details): DngPaymentRequest
    {
        if (in_array($reservation->status, [
            DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
            DngPaymentRequest::STATUS_NEEDS_REVIEW,
        ], true)) {
            return $reservation;
        }
        if ($reservation->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
            return $reservation;
        }

        $student = $this->studentReference($studentId);
        $payload = $this->providerPayload($student, $reservation, $details);
        try {
            $response = $this->dngPaymentService->pushReserved($payload);
        } catch (\Throwable $exception) {
            $this->holdUnknownProviderOutcome($reservation->id, $payload, $exception);

            throw $exception;
        }

        $finalized = $this->finalize($reservation->id, $response, $payload);

        if ($finalized->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
            $this->dngPaymentService->publishPushNotification(
                studentId: $studentId,
                campusId: $student->campusId,
                studentName: $student->fullName,
                requestId: $finalized->id,
                amount: (string) $finalized->amount,
                description: (string) $finalized->description,
            );
        }

        return $finalized;
    }

    /** @param array{Code: int, Type: string, Message: string, data: mixed} $response @param array<string, mixed> $payload */
    private function finalize(int $reservationId, array $response, array $payload): DngPaymentRequest
    {
        $billingAccountId = (int) DngPaymentRequest::query()->findOrFail($reservationId)->billing_account_id;

        return $this->guard()->handleIfChanged($billingAccountId, function ($_billingAccount, Closure $markChanged) use ($reservationId, $response, $payload): DngPaymentRequest {
            $reservation = DngPaymentRequest::query()->lockForUpdate()->findOrFail($reservationId);
            $targetIds = $reservation->reservationTargets()->pluck('invoice_line_id')->map(fn ($id): int => (int) $id)->all();
            InvoiceLine::query()->whereIn('id', $targetIds)->lockForUpdate()->get();
            $position = $this->settlementPositionReader->forPayableLines($targetIds);
            $currentTargets = $position->isValid() ? $this->collectibleTargets($position) : [];
            $currentTargetFingerprint = $this->fingerprint($currentTargets);
            $currentByLine = collect($currentTargets)->keyBy('invoice_line_id');
            $targets = $reservation->reservationTargets()->orderBy('invoice_line_id')->get()
                ->map(function (DngPaymentRequestReservationTarget $target) use ($currentByLine): ?array {
                    $current = $currentByLine->get((int) $target->invoice_line_id);
                    if ($current === null || Money::vnd((string) $target->captured_collectible)->isGreaterThan(Money::vnd($current['collectible']))) {
                        return null;
                    }

                    return [
                        'invoice_line_id' => (int) $target->invoice_line_id,
                        'finance_charge_id' => (int) $current['finance_charge_id'],
                        'collectible' => (string) $target->captured_collectible,
                        'identity' => (string) $target->target_identity,
                    ];
                })->filter()->values()->all();
            if ($targets === [] || $this->fingerprint($targets) !== $reservation->target_fingerprint) {
                $this->holdForReview(
                    $reservation,
                    $currentTargetFingerprint,
                    'Reserved Settlement Position targets changed before DNG finalization.',
                    $payload,
                    $response,
                );

                return $reservation->fresh();
            }
            $reservation->update([
                'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                'push_payload' => $payload,
                'push_response' => $response,
                'dng_transaction_id' => $response['data']['TransactionID'] ?? $response['data']['Id'] ?? null,
                'dng_payment_id' => $response['data']['PaymentId'] ?? $response['data']['OtherId'] ?? null,
            ]);
            $markChanged();

            return $reservation->fresh();
        });
    }

    /** @return list<array{invoice_line_id: int, finance_charge_id: int, collectible: string, identity: string}> */
    private function collectibleTargets(SettlementPosition $position, array $targetAmounts = []): array
    {
        $lineIds = collect($position->payable_line_breakdown)
            ->filter(fn (SettlementPosition $line): bool => $line->amounts !== null && $line->amounts->remaining->isPositive())
            ->map(fn (SettlementPosition $line): int => (int) $line->payable_line_id)->all();
        $lines = InvoiceLine::query()->whereIn('id', $lineIds)->get()->keyBy('id');

        return collect($position->payable_line_breakdown)
            ->filter(fn (SettlementPosition $line): bool => $line->amounts !== null && $line->amounts->remaining->isPositive())
            ->sortBy('payable_line_id')
            ->map(function (SettlementPosition $line) use ($lines, $targetAmounts): array {
                $invoiceLineId = (int) $line->payable_line_id;
                $invoiceLine = $lines->get($invoiceLineId);
                if ($invoiceLine === null || $invoiceLine->charge_id === null) {
                    throw new \RuntimeException("Payable line #{$invoiceLineId} cannot be reserved without a FinanceCharge.");
                }
                $collectible = $line->amounts->remaining;
                if (isset($targetAmounts[$invoiceLineId])) {
                    $requested = Money::vnd((string) $targetAmounts[$invoiceLineId]);
                    if (! $requested->isPositive()) {
                        throw new \RuntimeException("DNG target for payable line #{$invoiceLineId} must be positive.");
                    }
                    if ($requested->isGreaterThan($collectible)) {
                        throw new \RuntimeException("Payable line #{$invoiceLineId} no longer covers its installment target.");
                    }
                    if ($collectible->isGreaterThan($requested)) {
                        $collectible = $requested;
                    }
                }

                return [
                    'invoice_line_id' => $invoiceLineId,
                    'finance_charge_id' => (int) $invoiceLine->charge_id,
                    'collectible' => $collectible->amount,
                    'identity' => "invoice_line:{$invoiceLineId}",
                ];
            })->values()->all();
    }

    /** @param list<array{invoice_line_id: int, finance_charge_id: int, collectible: string, identity: string}> $targets */
    private function fingerprint(array $targets): string
    {
        return DngReservationTargetFingerprint::make($targets);
    }

    /**
     * Reuse is valid only when the current reservation request describes the
     * exact durable target already held by the provider identity.
     *
     * @param  array{description: string, semester_id: int, due_date: string, estimate_time: string}  $details
     * @param  list<array{invoice_line_id: int, finance_charge_id: int, collectible: string, identity: string}>  $targets
     * @param  array<int, int>  $installmentIdsByLine
     */
    private function isExactRetry(
        DngPaymentRequest $existing,
        StudentReference $student,
        BillingAccount $billingAccount,
        string $campusCode,
        string $feeType,
        array $details,
        string $slotKey,
        array $targets,
        Money $amount,
        string $targetFingerprint,
        array $installmentIdsByLine,
        int $sequence,
        bool $requireSettlementVersion,
    ): bool {
        $expectedTargets = collect($targets)
            ->map(fn (array $target): array => [
                'invoice_line_id' => (int) $target['invoice_line_id'],
                'captured_collectible' => (string) $target['collectible'],
                'target_identity' => (string) $target['identity'],
                'finance_charge_installment_id' => $installmentIdsByLine[(int) $target['invoice_line_id']] ?? null,
            ])
            ->sortBy('invoice_line_id')
            ->values()
            ->all();
        $storedTargets = $existing->reservationTargets()
            ->orderBy('invoice_line_id')
            ->get()
            ->map(fn (DngPaymentRequestReservationTarget $target): array => [
                'invoice_line_id' => (int) $target->invoice_line_id,
                'captured_collectible' => (string) $target->captured_collectible,
                'target_identity' => (string) $target->target_identity,
                'finance_charge_installment_id' => $target->finance_charge_installment_id === null ? null : (int) $target->finance_charge_installment_id,
            ])
            ->all();
        $expectedItemId = $this->itemId((int) $billingAccount->id, $campusCode, $feeType, $sequence);
        $settlementVersionMatches = $existing->captured_settlement_version !== null
            && (int) $billingAccount->settlement_version === (int) $existing->captured_settlement_version + 1;

        return ! ($existing->student_id !== $student->id
            || $existing->billing_account_id !== $billingAccount->id
            || $existing->student_code !== $student->studentCode
            || $existing->campus_code !== $campusCode
            || $existing->provider_rail !== self::PROVIDER_RAIL
            || $existing->fee_type !== $feeType
            || $existing->semester_id !== $details['semester_id']
            || $existing->active_slot_key !== $slotKey
            || (string) $existing->amount !== $amount->amount
            || $existing->target_fingerprint !== $targetFingerprint
            || $existing->item_id !== $expectedItemId
            || ($requireSettlementVersion && ! $settlementVersionMatches)
            || $storedTargets !== $expectedTargets);
    }

    /** @param array<string, mixed>|null $payload @param array<string, mixed>|null $response */
    private function holdForReview(
        DngPaymentRequest $reservation,
        string $currentTargetFingerprint,
        string $reason,
        ?array $payload = null,
        ?array $response = null,
    ): void {
        $this->guard()->handle((int) $reservation->billing_account_id, function () use ($reservation, $currentTargetFingerprint, $reason, $payload, $response): void {
            $lockedReservation = DngPaymentRequest::query()->lockForUpdate()->findOrFail($reservation->id);
            $lockedReservation->update([
                'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
                'push_payload' => $payload ?? $lockedReservation->push_payload,
                'push_response' => $response ?? $lockedReservation->push_response,
                'review_evidence' => [
                    'provider_response' => $response ?? $lockedReservation->push_response,
                    'deterministic_identity' => $lockedReservation->item_id,
                    'original_target_fingerprint' => $lockedReservation->target_fingerprint,
                    'current_target_fingerprint' => $currentTargetFingerprint,
                    'affected_installment_ids' => $this->affectedInstallmentIds($lockedReservation),
                ],
                'error_message' => $reason,
            ]);
        });
    }

    /** @param array<string, mixed> $payload */
    private function holdUnknownProviderOutcome(int $reservationId, array $payload, \Throwable $exception): void
    {
        $billingAccountId = (int) DngPaymentRequest::query()->findOrFail($reservationId)->billing_account_id;

        $this->guard()->handleIfChanged($billingAccountId, function ($_billingAccount, Closure $markChanged) use ($reservationId, $payload, $exception): void {
            $reservation = DngPaymentRequest::query()->lockForUpdate()->findOrFail($reservationId);
            $targetIds = $reservation->reservationTargets()->pluck('invoice_line_id')->map(fn ($id): int => (int) $id)->all();
            InvoiceLine::query()->whereIn('id', $targetIds)->lockForUpdate()->get();
            $position = $this->settlementPositionReader->forPayableLines($targetIds);
            $currentTargets = $position->isValid() ? $this->collectibleTargets($position) : [];

            $reservation->update([
                'status' => DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
                'push_payload' => $payload,
                'review_evidence' => [
                    'provider_response' => null,
                    'provider_exception' => $exception->getMessage(),
                    'deterministic_identity' => $reservation->item_id,
                    'original_target_fingerprint' => $reservation->target_fingerprint,
                    'current_target_fingerprint' => $this->fingerprint($currentTargets),
                    'affected_installment_ids' => $this->affectedInstallmentIds($reservation),
                ],
                'error_message' => $exception->getMessage(),
            ]);
            $markChanged();
        });
    }

    private function guard(): SettlementMutationGuard
    {
        return $this->settlementMutationGuard ?? app(SettlementMutationGuard::class);
    }

    /** @return list<int> */
    private function affectedInstallmentIds(DngPaymentRequest $reservation): array
    {
        return $reservation->reservationTargets()
            ->whereNotNull('finance_charge_installment_id')
            ->pluck('finance_charge_installment_id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    private function slotKey(int $billingAccountId, string $campusCode, string $feeType): string
    {
        return implode(':', [self::PROVIDER_RAIL, $campusCode, $billingAccountId, $feeType]);
    }

    private function itemId(int $billingAccountId, string $campusCode, string $feeType, int $sequence): string
    {
        return 'swinx-rsv-'.substr(hash('sha256', implode('|', [self::PROVIDER_RAIL, $campusCode, $billingAccountId, $feeType, $sequence])), 0, 32);
    }

    /** @param array{description: string, semester_id: int, due_date: string, estimate_time: string} $details @return array<string, mixed> */
    private function providerPayload(StudentReference $student, DngPaymentRequest $reservation, array $details): array
    {
        return [
            'campus_code' => $reservation->campus_code,
            'student_code' => $student->studentCode,
            'fee_type' => $reservation->fee_type,
            'type' => $reservation->fee_type,
            'note' => $reservation->description,
            'semester_id' => $details['semester_id'],
            'due_date' => $details['due_date'],
            'item_id' => $reservation->item_id,
            'amount' => $reservation->amount,
            'student_name' => $student->fullName,
            'email' => $student->email ?? '',
            'estimate_time' => $details['estimate_time'],
            'student_address' => $student->address ?? '',
            'cccd' => $student->nationalId,
        ];
    }

    private function studentReference(int $studentId): StudentReference
    {
        return ($this->studentReferences ?? app(StudentReferenceReader::class))->find($studentId)
            ?? throw new \RuntimeException("Student reference #{$studentId} cannot be resolved for the DNG payment request.");
    }

    private function assertRequiredPayerProfile(StudentReference $student): void
    {
        $errors = [];

        if (blank($student->studentCode)) {
            $errors['student_code'] = 'Không thể tạo yêu cầu DNG vì sinh viên chưa có mã sinh viên.';
        }
        if (blank($student->nationalId)) {
            $errors['cccd'] = 'Không thể tạo yêu cầu DNG vì sinh viên chưa có CCCD.';
        }
        if (blank($student->address)) {
            $errors['student_address'] = 'Không thể tạo yêu cầu DNG vì sinh viên chưa có địa chỉ.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
