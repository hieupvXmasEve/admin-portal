<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\AcademicDngPaymentProjectionSync;

/**
 * Captures attributable provider cash once, separately from target allocation.
 *
 * The command is deliberately shared by webhook and reconciliation paths. Its
 * caller supplies validation evidence; this command records the actual provider
 * receipt and delegates capped allocation to the canonical DNG bridge.
 */
class CaptureDngProviderReceiptAction
{
    public function __construct(
        private readonly DngPaymentService $dngPaymentService,
        private readonly ?AcademicDngPaymentProjectionSync $academicProjectionSync = null,
    ) {}

    /**
     * @param  array{request: DngPaymentRequest, receipt: array{amount: numeric-string|float|int, payload: array<string, mixed>, source: string, authenticity: array<string, mixed>, payer_correlation: array<string, mixed>, target_validation: array<string, mixed>}}  $data
     */
    public static function run(array $data): ?Payment
    {
        return app(self::class)->handle($data['request'], $data['receipt']);
    }

    /**
     * @param  array{amount: numeric-string|float|int, payload: array<string, mixed>, source: string, authenticity: array<string, mixed>, payer_correlation: array<string, mixed>, target_validation: array<string, mixed>}  $receipt
     */
    public function handle(DngPaymentRequest $request, array $receipt): ?Payment
    {
        $payment = $this->dngPaymentService->bridgeToPayment($request, $receipt);

        $issues = $receipt['target_validation']['issues'] ?? [];
        $exceptionType = null;
        $freshRequest = $request->fresh();
        if ($freshRequest !== null && in_array($freshRequest->status, [DngPaymentRequest::STATUS_CANCELLED, DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG], true)) {
            $exceptionType = 'payment_after_cancellation';
            $issues[] = 'Verified provider payment arrived after collection cancellation; payment was captured without reviving collection.';
        } elseif (($receipt['target_validation']['status'] ?? 'matched') !== 'matched') {
            $exceptionType = 'amount_mismatch';
        } elseif ($payment !== null
            && ($request->reservationTargets()->exists() || $request->chargeLinks()->exists())
            && $payment->fresh()->unapplied_amount > 0.01) {
            $exceptionType = 'target_drift';
            $issues[] = 'Current target collectible is lower than the verified provider receipt.';
        }

        if ($exceptionType !== null) {
            RegisterDngReceiptExceptionAction::run([
                'exception_type' => $exceptionType,
                'request' => $request,
                'payment' => $payment,
                'mismatch_reasons' => $issues !== [] ? $issues : ['Provider receipt requires target reconciliation.'],
                'raw_provider_evidence' => $receipt['payload'],
            ]);
            $request->fresh()?->update([
                'error_message' => 'Provider receipt captured; target allocation requires review: '
                    .implode('; ', $receipt['target_validation']['issues'] ?? []),
            ]);
        }

        if ($payment !== null) {
            $this->academicProjections()->syncForRequest($request->fresh() ?? $request);
        }

        return $payment;
    }

    private function academicProjections(): AcademicDngPaymentProjectionSync
    {
        return $this->academicProjectionSync ?? app(AcademicDngPaymentProjectionSync::class);
    }
}
