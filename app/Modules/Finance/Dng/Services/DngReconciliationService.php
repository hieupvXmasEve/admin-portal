<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Modules\Finance\Actions\CaptureDngProviderReceiptAction;
use App\Modules\Finance\Actions\RegisterDngReceiptExceptionAction;
use App\Modules\Finance\Actions\SettleInstallmentFromDngAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Closure;
use Illuminate\Support\Facades\Log;

class DngReconciliationService
{
    public function __construct(
        protected DngClient $dngClient,
        protected DngPaymentService $dngPaymentService,
        protected SettleInstallmentFromDngAction $settleInstallmentAction,
        protected ?BillingAccountProvisioner $billingAccountProvisioner = null,
        protected ?SettlementMutationGuard $settlementMutationGuard = null,
    ) {}

    /**
     * Reconcile DNG payments for a specific campus and date.
     *
     * @return array{backfilled: int, up_to_date: int, orphans: int, errors: int}
     */
    public function reconcileDay(string $campusCode, string $date): array
    {
        $summary = ['backfilled' => 0, 'up_to_date' => 0, 'orphans' => 0, 'errors' => 0];

        try {
            $response = $this->dngClient->checkPaidOfDay($campusCode, $date);
        } catch (\RuntimeException $e) {
            Log::error('DNG reconciliation: API call failed', [
                'campus_code' => $campusCode,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
            $summary['errors']++;

            return $summary;
        }

        $transactions = $response['data'] ?? [];
        if (! is_array($transactions)) {
            return $summary;
        }

        foreach ($transactions as $txn) {
            try {
                $this->processTransaction($txn, $campusCode, $summary);
            } catch (\Throwable $e) {
                Log::error('DNG reconciliation: transaction processing failed', [
                    'campus_code' => $campusCode,
                    'transaction' => $txn,
                    'error' => $e->getMessage(),
                ]);
                $summary['errors']++;
            }
        }

        Log::info('DNG reconciliation complete', [
            'campus_code' => $campusCode,
            'date' => $date,
            'summary' => $summary,
        ]);

        return $summary;
    }

    /**
     * Process a single transaction from the reconciliation response.
     *
     * @param  array<string, mixed>  $txn
     * @param  array<string, int>  $summary
     */
    private function processTransaction(array $txn, string $campusCode, array &$summary): void
    {
        $dngPaymentId = $txn['PaymentId'] ?? null;
        if (! $dngPaymentId) {
            return;
        }

        // Resolve the local request. ItemId + StudentId + Campus is the stable
        // settle-once correlation key; the DNG PaymentId is metadata that can
        // legitimately differ from the locally stored placeholder. Match on the
        // correlation key FIRST — matching PaymentId first would wrongly orphan a real
        // item-matched row whose stored placeholder differs from DNG's reported
        // PaymentId. Fall back to PaymentId only when no ItemId correlation is given.
        $itemId = (string) ($txn['ItemId'] ?? '');
        $studentId = (string) ($txn['StudentId'] ?? '');
        $request = null;

        if ($itemId !== '' && $studentId !== '') {
            $request = DngPaymentRequest::where('item_id', $itemId)
                ->where('student_code', $studentId)
                ->where('campus_code', $campusCode)
                ->first();
        }

        if (! $request) {
            $request = DngPaymentRequest::where('dng_payment_id', $dngPaymentId)->first();
        }

        if (! $request) {
            RegisterDngReceiptExceptionAction::run([
                'exception_type' => 'unmatched_provider_receipt',
                'mismatch_reasons' => ["No DNG payment request found for PaymentId: {$dngPaymentId}"],
                'raw_provider_evidence' => $txn,
            ]);
            // Orphan: DNG knows about a payment we don't have locally
            Log::warning('DNG reconciliation: orphan payment found', [
                'dng_payment_id' => $dngPaymentId,
                'campus_code' => $campusCode,
                'student_id' => $txn['StudentId'] ?? 'unknown',
            ]);
            $summary['orphans']++;

            return;
        }

        // Surface (but do not fail on) a PaymentId that differs from the stored
        // placeholder: the row is correlated by ItemId, the placeholder is kept, and
        // only a null dng_payment_id is bound later in the locked transition.
        if ($request->dng_payment_id !== null && $request->dng_payment_id !== $dngPaymentId) {
            Log::info('DNG reconciliation: PaymentId differs from stored value; reconciling by ItemId', [
                'dng_payment_request_id' => $request->id,
                'stored_dng_payment_id' => $request->dng_payment_id,
                'callback_dng_payment_id' => $dngPaymentId,
            ]);
        }

        $receiptPayload = [
            'Amount' => $txn['Amount'] ?? null,
            'StudentId' => $txn['StudentId'] ?? null,
            'CampusCode' => $campusCode,
            'ItemId' => $txn['ItemId'] ?? null,
        ];
        $mismatchReasons = $request->receiptCorrelationMismatchReasons($receiptPayload);

        if ($mismatchReasons !== []) {
            RegisterDngReceiptExceptionAction::run([
                'exception_type' => 'unmatched_provider_receipt',
                'request' => $request,
                'mismatch_reasons' => $mismatchReasons,
                'raw_provider_evidence' => $txn,
            ]);
            Log::warning('DNG reconciliation: payload mismatch', [
                'dng_payment_request_id' => $request->id,
                'dng_payment_id' => $dngPaymentId,
                'issues' => $mismatchReasons,
            ]);
            $summary['errors']++;

            return;
        }

        $hasInvoice = filled($txn['InvoiceSerialNumber'] ?? null)
            && filled($txn['InvoiceDate'] ?? null);

        $targetStatus = $hasInvoice
            ? DngPaymentRequest::STATUS_PAID_INVOICED
            : DngPaymentRequest::STATUS_PAID_UNINVOICED;

        // P1: decide + apply the transition under a row lock with a FRESH re-read so a
        // concurrent webhook (e.g. Call 2 already advanced to paid_invoiced) cannot be
        // downgraded by a stale reconciliation read. Mirrors DngWebhookService so the
        // webhook-vs-reconciliation race is serialised, not just webhook-vs-webhook.
        $outcome = $this->guard()->handleIfChanged($this->billingAccountId($request), function ($_billingAccount, Closure $markChanged) use ($request, $txn, $hasInvoice, $targetStatus, $dngPaymentId): array {
            /** @var DngPaymentRequest $locked */
            $locked = DngPaymentRequest::query()->lockForUpdate()->find($request->id);

            // FIN-18: both cancellation states are terminal — never revive/bridge them.
            if (in_array($locked->status, [
                DngPaymentRequest::STATUS_CANCELLED,
                DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG,
            ], true)) {
                return ['result' => 'cancelled', 'status' => $locked->status];
            }

            // Bind the DNG PaymentId now that the mismatch check has passed (deferred
            // from the fallback match so a non-matching DNG row cannot poison it).
            if (! $locked->dng_payment_id) {
                $locked->update(['dng_payment_id' => $dngPaymentId]);
                $markChanged();
            }

            $statusOrder = $this->statusOrder();
            $currentOrder = $statusOrder[$locked->status] ?? 0;
            $targetOrder = $statusOrder[$targetStatus] ?? 0;

            if ($currentOrder >= $targetOrder) {
                return ['result' => 'up_to_date', 'status' => $locked->status];
            }

            // Backfill: refuse an illegal transition BEFORE mutating fields, so a
            // request that cannot legally reach the target (e.g. a failed request) is
            // never partially backfilled — and is not bridged/settled below either.
            if (! $locked->canTransitionTo($targetStatus)) {
                return ['result' => 'cannot_transition', 'from' => $locked->status];
            }

            $updateData = [
                'last_callback_payload' => $txn,
                'psp_code' => $txn['PSPCode'] ?? $locked->psp_code,
            ];

            if (! $locked->paid_at) {
                $updateData['paid_at'] = now();
            }

            if ($hasInvoice) {
                $updateData['invoice_serial_number'] = $txn['InvoiceSerialNumber'];
                $updateData['invoice_date'] = $txn['InvoiceDate'];
            }

            $locked->update($updateData);
            $locked->transitionTo($targetStatus);
            $markChanged();

            return ['result' => 'backfilled'];
        });

        if ($outcome['result'] === 'cancelled') {
            $this->captureProviderReceipt($request->fresh(), $txn);
            Log::info('DNG reconciliation: skipped cancelled request', [
                'dng_payment_request_id' => $request->id,
                'dng_payment_id' => $dngPaymentId,
                'status' => $outcome['status'],
            ]);
            $summary['up_to_date']++;

            return;
        }

        if ($outcome['result'] === 'cannot_transition') {
            // Mirror the webhook: an illegal transition is an error, not a backfill —
            // do not create a Payment or settle installments for it.
            Log::warning('DNG reconciliation: refused illegal transition', [
                'dng_payment_request_id' => $request->id,
                'dng_payment_id' => $dngPaymentId,
                'from' => $outcome['from'],
                'to' => $targetStatus,
            ]);
            $summary['errors']++;

            return;
        }

        if ($outcome['result'] === 'up_to_date') {
            // P2: even when already at/past the target status, make sure BOTH the
            // canonical Payment and the linked installment are settled — a prior
            // attempt may have advanced status but crashed before completing them.
            // Both operations are idempotent (bridge guards on payment_id; settle
            // skips already-paid installments).
            if (in_array($outcome['status'], [
                DngPaymentRequest::STATUS_PAID_UNINVOICED,
                DngPaymentRequest::STATUS_PAID_INVOICED,
                DngPaymentRequest::STATUS_RECONCILED,
            ], true)) {
                $fresh = $request->fresh();
                $this->captureProviderReceipt($fresh, $txn);
                if ($fresh->receiptAmountMismatchReasons($txn) === []) {
                    $this->settleInstallmentAction->handle($request->fresh());
                }
            }

            $summary['up_to_date']++;

            return;
        }

        // result === 'backfilled'
        $fresh = $request->fresh();

        // Bridge to canonical Payment if not yet done
        $this->captureProviderReceipt($fresh, $txn);

        // Settle linked installment + dispatch next push (no-op if not installment-linked).
        // Idempotent: SettleInstallmentFromDngAction skips when installment is already paid.
        if ($fresh->receiptAmountMismatchReasons($txn) === []) {
            $this->settleInstallmentAction->handle($request->fresh());
        }

        $summary['backfilled']++;

        Log::info('DNG reconciliation: backfilled payment', [
            'dng_payment_request_id' => $request->id,
            'dng_payment_id' => $dngPaymentId,
            'new_status' => $request->fresh()->status,
        ]);
    }

    private function guard(): SettlementMutationGuard
    {
        return $this->settlementMutationGuard ?? app(SettlementMutationGuard::class);
    }

    private function billingAccountId(DngPaymentRequest $request): int
    {
        if ($request->billing_account_id !== null) {
            return (int) $request->billing_account_id;
        }

        return (int) ($this->billingAccountProvisioner ?? app(BillingAccountProvisioner::class))
            ->forStudent((int) $request->student_id)
            ->id;
    }

    /**
     * Status ordering for comparison (higher = further along).
     *
     * @return array<string, int>
     */
    private function statusOrder(): array
    {
        return [
            DngPaymentRequest::STATUS_PENDING => 0,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG => 1,
            DngPaymentRequest::STATUS_PAID_UNINVOICED => 2,
            DngPaymentRequest::STATUS_PAID_INVOICED => 3,
            DngPaymentRequest::STATUS_RECONCILED => 4,
            DngPaymentRequest::STATUS_NEEDS_REVIEW => 1,
            DngPaymentRequest::STATUS_FAILED => -1,
            DngPaymentRequest::STATUS_CANCELLED => -2,
            // FIN-18: cancel pushed to DNG is terminal (was missing → default 0).
            DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG => -2,
        ];
    }

    /** @param array<string, mixed> $transaction */
    private function captureProviderReceipt(DngPaymentRequest $request, array $transaction): void
    {
        $amountIssues = $request->receiptAmountMismatchReasons($transaction);

        (new CaptureDngProviderReceiptAction($this->dngPaymentService))->handle($request, [
            'amount' => $transaction['Amount'] ?? $request->amount,
            'payload' => $transaction,
            'source' => 'daily_reconciliation',
            'authenticity' => ['status' => 'provider_authenticated_query'],
            'payer_correlation' => ['status' => 'matched'],
            'target_validation' => [
                'status' => $amountIssues === [] ? 'matched' : 'amount_mismatch',
                'issues' => $amountIssues,
            ],
        ]);
    }
}
