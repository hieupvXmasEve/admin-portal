<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngClient;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Cancels collection only. It never voids the underlying obligation, charge,
 * installment, or source workflow.
 */
class CancelDngPaymentRequestAction
{
    public function __construct(
        private readonly DngClient $dngClient,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
    ) {}

    /**
     * An unattempted reservation is released locally. A pushed request is only
     * released after a provider-confirmed cancellation; ambiguous provider calls
     * remain held as unknown outcomes.
     *
     * @throws \RuntimeException if the request status cannot be cancelled.
     * @throws \Throwable if the provider cancellation outcome is unknown.
     */
    public function run(DngPaymentRequest $request): void
    {
        if ($request->status === DngPaymentRequest::STATUS_PENDING) {
            $this->settlementMutationGuard->handleIfChanged(
                $this->billingAccountId($request),
                function ($_billingAccount, Closure $markChanged) use ($request): void {
                    $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
                    $locked->transitionTo(DngPaymentRequest::STATUS_CANCELLED);
                    $markChanged();
                },
            );

            return;
        }

        if ($request->status !== DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
            throw new \RuntimeException(
                "Cannot cancel DNG payment request #{$request->id}: current status '{$request->status}' does not allow cancellation."
            );
        }

        $this->cancelPushedRequest($request);
    }

    /**
     * Close an unpaid request in Swinx for a terminal student lifecycle state.
     *
     * This intentionally does not call DNG. Late verified payment evidence is
     * still captured without reviving the locally cancelled collection.
     */
    public function runLocallyForLifecycle(DngPaymentRequest $request): void
    {
        $this->settlementMutationGuard->handleIfChanged(
            $this->billingAccountId($request),
            function ($_billingAccount, Closure $markChanged) use ($request): void {
                $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);

                if ($locked->status === DngPaymentRequest::STATUS_CANCELLED) {
                    return;
                }

                if ($locked->payment_id !== null) {
                    throw new \RuntimeException(
                        "Cannot locally close DNG payment request #{$request->id}: canonical Payment #{$locked->payment_id} is already linked."
                    );
                }

                if (! in_array($locked->status, [
                    DngPaymentRequest::STATUS_PENDING,
                    DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                    DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
                    DngPaymentRequest::STATUS_NEEDS_REVIEW,
                ], true)) {
                    throw new \RuntimeException(
                        "Cannot locally close DNG payment request #{$request->id} for lifecycle: current status '{$locked->status}' is not unpaid and cancellable."
                    );
                }

                $locked->transitionTo(DngPaymentRequest::STATUS_CANCELLED);
                $markChanged();
            },
        );
    }

    private function cancelPushedRequest(DngPaymentRequest $request): void
    {
        $originalData = $this->originalData($request);
        $cancelPayload = $this->dngClient->buildInsertNewRecordPayload([...$originalData, 'amount' => -1]);

        try {
            // Provider calls intentionally happen outside every DB transaction/lock.
            $response = $this->dngClient->cancelRecord($originalData);
        } catch (\Throwable $exception) {
            if ($this->isProviderRejection($exception)) {
                $this->recordProviderRejection($request, $cancelPayload, $exception);

                throw $exception;
            }

            $this->recordUnknownOutcome($request, $cancelPayload, $exception);

            throw $exception;
        }

        $this->settlementMutationGuard->handleIfChanged(
            $this->billingAccountId($request),
            function ($_billingAccount, Closure $markChanged) use ($request, $cancelPayload, $response): void {
                $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
                $locked->update([
                    'cancel_push_payload' => $cancelPayload,
                    'cancel_push_response' => $response,
                    'error_message' => null,
                ]);
                $markChanged();

                if ($locked->status === DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
                    $locked->transitionTo(DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG);

                    RegisterDngReceiptExceptionAction::run([
                        'exception_type' => 'cancel_confirmed',
                        'request' => $locked,
                        'hold_request' => false,
                        'mismatch_reasons' => ['Provider confirmed collection cancellation. Underlying obligations remain active.'],
                        'raw_provider_evidence' => ['cancel_payload' => $cancelPayload, 'cancel_response' => $response],
                    ]);

                    return;
                }

                if (in_array($locked->status, [DngPaymentRequest::STATUS_PAID_UNINVOICED, DngPaymentRequest::STATUS_PAID_INVOICED, DngPaymentRequest::STATUS_RECONCILED], true)) {
                    RegisterDngReceiptExceptionAction::run([
                        'exception_type' => 'payment_during_cancellation',
                        'request' => $locked,
                        'hold_request' => false,
                        'mismatch_reasons' => ['Provider payment was confirmed while cancellation was in progress.'],
                        'raw_provider_evidence' => ['cancel_payload' => $cancelPayload, 'cancel_response' => $response],
                    ]);
                }
            },
        );
    }

    /** @return array<string, string|null> */
    private function originalData(DngPaymentRequest $request): array
    {
        $pushPayload = $request->push_payload ?? [];

        return [
            'student_code' => $request->student_code,
            'campus_code' => $request->campus_code,
            'type' => $request->fee_type,
            'item_id' => $request->item_id,
            'student_name' => $pushPayload['StudentName'] ?? '',
            'email' => $pushPayload['Email'] ?? '',
            'estimate_time' => $pushPayload['EstimateTime'] ?? '',
            'student_address' => $pushPayload['StudentAddress'] ?? '',
            'cccd' => $pushPayload['CCCD'] ?? null,
        ];
    }

    private function recordUnknownOutcome(DngPaymentRequest $request, array $cancelPayload, \Throwable $exception): void
    {
        Log::warning('DNG cancellation outcome is unknown', [
            'dng_payment_request_id' => $request->id,
            'error' => $exception->getMessage(),
        ]);

        $this->settlementMutationGuard->handleIfChanged(
            $this->billingAccountId($request),
            function ($_billingAccount, Closure $markChanged) use ($request, $cancelPayload, $exception): void {
                $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
                $locked->update(['cancel_push_payload' => $cancelPayload]);
                $markChanged();

                if ($locked->status !== DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
                    return;
                }

                $locked->transitionTo(DngPaymentRequest::STATUS_UNKNOWN_OUTCOME);
                $locked->update(['error_message' => 'Unknown collection outcome: '.$exception->getMessage()]);

                RegisterDngReceiptExceptionAction::run([
                    'exception_type' => 'unknown_collection_outcome',
                    'request' => $locked,
                    'hold_request' => false,
                    'mismatch_reasons' => ['Provider cancellation outcome is unknown. Keep collection blocked until reconciliation confirms the outcome.'],
                    'raw_provider_evidence' => ['cancel_payload' => $cancelPayload, 'error' => $exception->getMessage()],
                ]);
            },
        );
    }

    private function isProviderRejection(\Throwable $exception): bool
    {
        return str_starts_with($exception->getMessage(), 'DNG API error [');
    }

    private function recordProviderRejection(DngPaymentRequest $request, array $cancelPayload, \Throwable $exception): void
    {
        $this->settlementMutationGuard->handleIfChanged(
            $this->billingAccountId($request),
            function ($_billingAccount, Closure $markChanged) use ($request, $cancelPayload, $exception): void {
                $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
                $locked->update(['cancel_push_payload' => $cancelPayload]);
                $markChanged();

                if ($locked->status !== DngPaymentRequest::STATUS_PUSHED_TO_DNG) {
                    return;
                }

                $locked->transitionTo(DngPaymentRequest::STATUS_NEEDS_REVIEW);
                $locked->update(['error_message' => 'Provider rejected collection cancellation: '.$exception->getMessage()]);

                RegisterDngReceiptExceptionAction::run([
                    'exception_type' => 'cancel_rejected',
                    'request' => $locked,
                    'hold_request' => false,
                    'mismatch_reasons' => ['Provider rejected collection cancellation. The request remains active until Finance verifies or retries it.'],
                    'raw_provider_evidence' => ['cancel_payload' => $cancelPayload, 'error' => $exception->getMessage()],
                ]);
            },
        );
    }

    private function billingAccountId(DngPaymentRequest $request): int
    {
        if ($request->billing_account_id !== null) {
            return (int) $request->billing_account_id;
        }

        $billingAccountId = (int) $this->billingAccountProvisioner->forStudent((int) $request->student_id)->id;
        $this->settlementMutationGuard->handleIfChanged(
            $billingAccountId,
            function ($_billingAccount, Closure $markChanged) use ($request, $billingAccountId): void {
                $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
                if ($locked->billing_account_id !== null) {
                    return;
                }

                $locked->update(['billing_account_id' => $billingAccountId]);
                $request->setAttribute('billing_account_id', $billingAccountId);
                $markChanged();
            },
        );

        return $billingAccountId;
    }
}
