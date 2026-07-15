<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Illuminate\Support\Facades\DB;

class RegisterDngReceiptExceptionAction
{
    public function __construct(
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
    ) {}

    /** @param array{exception_type: string, mismatch_reasons: list<string>, raw_provider_evidence: array<string, mixed>, hold_request?: bool, request?: DngPaymentRequest|null, webhook_event?: DngWebhookEvent|null, payment?: Payment|null} $data */
    public static function run(array $data): DngReceiptException
    {
        return app(self::class)->handle($data);
    }

    /** @param array{exception_type: string, mismatch_reasons: list<string>, raw_provider_evidence: array<string, mixed>, hold_request?: bool, request?: DngPaymentRequest|null, webhook_event?: DngWebhookEvent|null, payment?: Payment|null} $data */
    public function handle(array $data): DngReceiptException
    {
        /** @var DngPaymentRequest|null $request */
        $request = $data['request'] ?? null;
        if ($request !== null) {
            $billingAccountId = $request->billing_account_id
                ?? $this->billingAccountProvisioner->forStudent((int) $request->student_id)->id;

            return $this->settlementMutationGuard->handleIfChanged(
                (int) $billingAccountId,
                function ($_billingAccount, \Closure $markChanged) use ($data, $request, $billingAccountId): DngReceiptException {
                    return DB::transaction(function () use ($data, $request, $markChanged, $billingAccountId): DngReceiptException {
                        if ($request->billing_account_id === null) {
                            $request->update(['billing_account_id' => $billingAccountId]);
                            $markChanged();
                        }

                        $exception = $this->firstOrCreateException($data, $request);
                        if ($exception->wasRecentlyCreated) {
                            $markChanged();
                        }

                        if (($data['hold_request'] ?? true)
                            && ! in_array($request->fresh()->status, [DngPaymentRequest::STATUS_CANCELLED, DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG, DngPaymentRequest::STATUS_UNKNOWN_OUTCOME], true)) {
                            $freshRequest = $request->fresh();
                            $freshRequest->forceFill([
                                'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
                                'error_message' => 'Cần kiểm tra: '.implode('; ', $data['mismatch_reasons']),
                            ]);

                            if ($freshRequest->isDirty()) {
                                $freshRequest->save();
                                $markChanged();
                            }
                        }

                        return $exception;
                    });
                },
            );
        }

        return DB::transaction(function () use ($data): DngReceiptException {
            return $this->firstOrCreateException($data, null);
        });
    }

    /** @param array{exception_type: string, mismatch_reasons: list<string>, raw_provider_evidence: array<string, mixed>, hold_request?: bool, request?: DngPaymentRequest|null, webhook_event?: DngWebhookEvent|null, payment?: Payment|null} $data */
    private function firstOrCreateException(array $data, ?DngPaymentRequest $request): DngReceiptException
    {
        $event = $data['webhook_event'] ?? null;
        $payment = $data['payment'] ?? null;
        $evidence = $data['raw_provider_evidence'];
        $evidenceHash = hash('sha256', json_encode(['type' => $data['exception_type'], 'source' => $event?->id, 'evidence' => $evidence], JSON_THROW_ON_ERROR));

        /** @var DngReceiptException $exception */
        $exception = DngReceiptException::query()->firstOrCreate(
            ['evidence_hash' => $evidenceHash],
            [
                'exception_type' => $data['exception_type'],
                'dng_payment_request_id' => $request?->id,
                'dng_webhook_event_id' => $event?->id,
                'payment_id' => $payment?->id,
                'provider_payment_id' => $evidence['PaymentId'] ?? null,
                'mismatch_reasons' => $data['mismatch_reasons'],
                'affected_scope' => $this->affectedScope($request),
                'raw_provider_evidence' => $evidence,
            ],
        );

        return $exception;
    }

    /** @return array{dng_payment_request_id: int|null, billing_account_id: int|null, invoice_line_ids: list<int>} */
    private function affectedScope(?DngPaymentRequest $request): array
    {
        if ($request === null) {
            return ['dng_payment_request_id' => null, 'billing_account_id' => null, 'invoice_line_ids' => []];
        }

        return [
            'dng_payment_request_id' => $request->id,
            'billing_account_id' => $request->billing_account_id,
            'invoice_line_ids' => $request->reservationTargets()->pluck('invoice_line_id')->map(static fn (int|string $id): int => (int) $id)->all(),
        ];
    }
}
