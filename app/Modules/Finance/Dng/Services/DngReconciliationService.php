<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Support\Facades\Log;

class DngReconciliationService
{
    public function __construct(
        protected DngClient $dngClient,
        protected DngPaymentService $dngPaymentService,
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

        // Find existing request
        $request = DngPaymentRequest::where('dng_payment_id', $dngPaymentId)->first();

        if (! $request) {
            // Try fallback match
            $request = DngPaymentRequest::where('item_id', $txn['ItemId'] ?? '')
                ->where('student_code', $txn['StudentId'] ?? '')
                ->where('campus_code', $campusCode)
                ->whereNull('dng_payment_id')
                ->first();

            if ($request) {
                $request->update(['dng_payment_id' => $dngPaymentId]);
            }
        }

        if (! $request) {
            // Orphan: DNG knows about a payment we don't have locally
            Log::warning('DNG reconciliation: orphan payment found', [
                'dng_payment_id' => $dngPaymentId,
                'campus_code' => $campusCode,
                'student_id' => $txn['StudentId'] ?? 'unknown',
            ]);
            $summary['orphans']++;

            return;
        }

        $mismatchReasons = $request->callbackMismatchReasons([
            'Amount' => $txn['Amount'] ?? null,
            'StudentId' => $txn['StudentId'] ?? null,
            'CampusCode' => $campusCode,
        ]);

        if ($mismatchReasons !== []) {
            Log::warning('DNG reconciliation: payload mismatch', [
                'dng_payment_request_id' => $request->id,
                'dng_payment_id' => $dngPaymentId,
                'issues' => $mismatchReasons,
            ]);
            $summary['errors']++;

            return;
        }

        // Check if local record needs updating
        $hasInvoice = filled($txn['InvoiceSerialNumber'] ?? null)
            && filled($txn['InvoiceDate'] ?? null);

        $targetStatus = $hasInvoice
            ? DngPaymentRequest::STATUS_PAID_INVOICED
            : DngPaymentRequest::STATUS_PAID_UNINVOICED;

        // Already up to date or further along?
        $statusOrder = $this->statusOrder();
        $currentOrder = $statusOrder[$request->status] ?? 0;
        $targetOrder = $statusOrder[$targetStatus] ?? 0;

        if ($currentOrder >= $targetOrder) {
            if (
                ! $request->hasBridgedPayment()
                && in_array($request->status, [
                    DngPaymentRequest::STATUS_PAID_UNINVOICED,
                    DngPaymentRequest::STATUS_PAID_INVOICED,
                    DngPaymentRequest::STATUS_RECONCILED,
                ], true)
            ) {
                $this->dngPaymentService->bridgeToPayment($request);
            }

            $summary['up_to_date']++;

            return;
        }

        // Backfill: update local record
        $updateData = [
            'last_callback_payload' => $txn,
            'psp_code' => $txn['PSPCode'] ?? $request->psp_code,
        ];

        if (! $request->paid_at) {
            $updateData['paid_at'] = now();
        }

        if ($hasInvoice) {
            $updateData['invoice_serial_number'] = $txn['InvoiceSerialNumber'];
            $updateData['invoice_date'] = $txn['InvoiceDate'];
        }

        $request->update($updateData);

        if ($request->canTransitionTo($targetStatus)) {
            $request->transitionTo($targetStatus);
        }

        // Bridge to canonical Payment if not yet done
        $this->dngPaymentService->bridgeToPayment($request);

        $summary['backfilled']++;

        Log::info('DNG reconciliation: backfilled payment', [
            'dng_payment_request_id' => $request->id,
            'dng_payment_id' => $dngPaymentId,
            'new_status' => $request->fresh()->status,
        ]);
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
            DngPaymentRequest::STATUS_FAILED => -1,
        ];
    }
}
