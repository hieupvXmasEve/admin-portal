<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Models\Department;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DngPaymentService
{
    public function __construct(
        protected DngClient $dngClient,
        protected PaymentService $paymentService,
        protected PublishDomainEventAction $publishDomainEventAction,
        protected ?BillingAccountProvisioner $billingAccountProvisioner = null,
        protected ?SettlementMutationGuard $settlementMutationGuard = null,
    ) {}

    /**
     * Send an already-persisted guarded reservation to DNG.
     *
     * Persistence/finalization deliberately belongs to the reservation action so
     * this external call cannot accidentally run under its payer transaction.
     *
     * @param  array<string, mixed>  $chargeData
     * @return array{Code: int, Type: string, Message: string, data: mixed}
     */
    public function pushReserved(array $chargeData): array
    {
        $payload = $this->dngClient->buildInsertNewRecordPayload($chargeData);

        return $this->dngClient->insertNewRecord($chargeData, $payload);
    }

    /**
     * Build a payment access payload for QR/virtual account flow.
     *
     * @param  array<int, string>  $feeTypes
     * @return array<string, mixed>
     */
    public function createQrAccess(DngPaymentRequest $request, array $feeTypes): array
    {
        return $this->dngClient->createVirtualAccountByFeeType([
            'student_code' => $request->student_code,
            'campus_code' => $request->campus_code,
            'fee_types' => $feeTypes,
        ]);
    }

    /**
     * Build a payment access payload for installment/Foxpay flow.
     *
     * @param  array<int, string>  $feeTypes
     * @return array<string, mixed>
     */
    public function createInstallmentAccess(DngPaymentRequest $request, array $feeTypes): array
    {
        return $this->dngClient->createFoxpayPaymentByFeeType([
            'student_code' => $request->student_code,
            'campus_code' => $request->campus_code,
            'fee_types' => $feeTypes,
        ]);
    }

    /**
     * Publish a notification (realtime + email) after a DNG payment request is pushed successfully.
     */
    private function publishPushNotification(
        int $studentId,
        ?int $campusId,
        string $studentName,
        int $requestId,
        string $amount,
        string $description,
    ): void {
        try {
            $formattedAmount = number_format((float) $amount, 0, ',', '.').' VNĐ';

            $envelope = new DomainEventEnvelope(
                eventId: (string) Str::uuid(),
                eventName: 'finance.dng_payment_pushed',
                eventVersion: 1,
                occurredAt: CarbonImmutable::now(),
                aggregateType: 'dng_payment_request',
                aggregateId: (string) $requestId,
                campusId: $campusId,
                actorUserId: null,
                payload: [
                    'type_key' => 'dng_payment_pushed',
                    'channels' => ['email', 'realtime'],
                    'recipient_targets' => [
                        ['type' => 'student', 'id' => $studentId],
                    ],
                    'data' => [
                        'title' => 'Yêu cầu thanh toán đã được tạo',
                        'body' => "Xin chào {$studentName}, bạn có 1 khoản phí {$formattedAmount} ({$description}). Vui lòng vào mục DNG Payment để hoàn tất thanh toán.",
                        'category' => 'finance',
                        'is_important' => true,
                        'action_text' => 'Xem chi tiết',
                        'action_type' => 'finance.dng_payment_request',
                        'action_params' => [],
                        'dng_payment_request_id' => $requestId,
                        'student_name' => $studentName,
                    ],
                ],
            );

            $this->publishDomainEventAction->run($envelope);
        } catch (\Throwable $e) {
            Log::warning('Failed to publish DNG push notification', [
                'student_id' => $studentId,
                'dng_payment_request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function publishAllocationNotification(
        DngPaymentRequest $request,
        Payment $payment,
        Collection $allocations,
    ): void {
        try {
            $deptId = Department::query()->where('code', 'HQ')->value('id');

            if ($deptId === null) {
                Log::warning('DNG allocation notification: HQ department not found', [
                    'dng_payment_request_id' => $request->id,
                ]);

                return;
            }

            $studentName = $request->student?->full_name ?? $request->student_code;
            $studentCode = $request->student_code;
            $formattedAmount = number_format((float) $payment->amount, 0, ',', '.').' VNĐ';

            if ($allocations->isNotEmpty()) {
                $count = $allocations->count();
                $allocatedTotal = number_format((float) $allocations->sum('amount'), 0, ',', '.').' VNĐ';
                $title = 'Phân bổ thanh toán thành công';
                $body = "Đã phân bổ tự động {$count} khoản phí / {$allocatedTotal} từ thanh toán của SV {$studentName} ({$studentCode})";
            } else {
                $title = 'Cảnh báo: Không phân bổ được thanh toán';
                $body = "Cảnh báo: Thanh toán {$formattedAmount} của SV {$studentName} ({$studentCode}) nhận được nhưng không tìm thấy khoản phí tồn đọng để phân bổ";
            }

            $envelope = new DomainEventEnvelope(
                eventId: (string) Str::uuid(),
                eventName: 'finance.dng_payment_allocated',
                eventVersion: 1,
                occurredAt: CarbonImmutable::now(),
                aggregateType: 'dng_payment_request',
                aggregateId: (string) $request->id,
                campusId: null,
                actorUserId: null,
                payload: [
                    'type_key' => 'dng_payment_allocated',
                    'channels' => ['realtime'],
                    'recipient_targets' => [
                        ['type' => 'department', 'id' => $deptId],
                    ],
                    'data' => [
                        'title' => $title,
                        'body' => $body,
                        'category' => 'finance',
                        'is_important' => $allocations->isEmpty(),
                        'student_name' => $studentName,
                        'student_code' => $studentCode,
                        'amount_formatted' => $formattedAmount,
                        'allocated_count' => $allocations->count(),
                        'dng_payment_request_id' => $request->id,
                    ],
                ],
            );

            $this->publishDomainEventAction->runAfterCommit($envelope);
        } catch (\Throwable $e) {
            Log::warning('Failed to publish DNG allocation notification', [
                'dng_payment_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bridge a confirmed DNG payment into the canonical Payment system.
     * Called when a callback confirms the student has paid.
     *
     * Guard: skip if payment_id already set (idempotent).
     */
    /**
     * @param  array{amount?: numeric-string|float|int, payload?: array<string, mixed>, source?: string, authenticity?: array<string, mixed>, payer_correlation?: array<string, mixed>, target_validation?: array<string, mixed>}|null  $receipt
     */
    public function bridgeToPayment(DngPaymentRequest $request, ?array $receipt = null): ?Payment
    {
        if ($request->hasBridgedPayment()) {
            return $request->payment;
        }

        $billingAccountId = $this->billingAccountId($request);
        $payment = $this->guard()->handleIfChanged($billingAccountId, function () use ($request, $receipt) {
            // Lock row to prevent concurrent bridge (webhook + reconciliation race)
            $request = DngPaymentRequest::lockForUpdate()->find($request->id);
            if ($request->hasBridgedPayment()) {
                return $request->payment;
            }

            $receiptAmount = $receipt['amount'] ?? $request->amount;
            $providerReceipt = $receipt['payload'] ?? $request->last_callback_payload;
            $providerPaymentId = is_array($providerReceipt)
                ? $providerReceipt['PaymentId'] ?? null
                : null;

            $payment = $this->paymentService->recordPayment([
                'student_id' => $request->student_id,
                'amount' => $receiptAmount,
                'method' => Payment::METHOD_GATEWAY,
                'source' => 'dng',
                'external_ref' => $providerPaymentId ?? $request->dng_payment_id,
                'paid_at' => $request->paid_at ?? now(),
                'status' => Payment::STATUS_COMPLETED,
                'raw_payload' => [
                    'dng_payment_request_id' => $request->id,
                    'provider_receipt' => $providerReceipt,
                    'receipt_source' => $receipt['source'] ?? 'legacy_bridge',
                    'authenticity' => $receipt['authenticity'] ?? null,
                    'payer_correlation' => $receipt['payer_correlation'] ?? null,
                    'target_validation' => $receipt['target_validation'] ?? null,
                ],
                'received_by_user_id' => null,
            ]);

            $request->update(['payment_id' => $payment->id]);

            return $payment;
        });

        $request = $request->fresh();
        $allocations = collect();

        try {
            // Capture is committed before allocation. Allocation may legitimately
            // find target drift; then the verified provider cash remains as Còn dư.
            // PaymentService locks and caps every revalidated target by its current
            // outstanding amount, so stale reservation amounts cannot over-apply.
            $chargeLinks = $request->chargeLinks()->with('financeCharge')->get();

            if ($chargeLinks->isNotEmpty()) {
                foreach ($chargeLinks as $link) {
                    $result = $this->paymentService->allocatePayment(
                        $payment->id,
                        [$link->finance_charge_id => (float) $link->amount],
                        allowHeldTargets: true,
                    );
                    $allocations = $allocations->merge($result);
                }
            } else {
                $allocations = $this->paymentService->autoAllocatePayment($payment->id);
            }
        } catch (\Throwable $e) {
            $this->guard()->handle($billingAccountId, function () use ($request, $e): void {
                $request->fresh()->update([
                    'error_message' => 'Provider receipt captured; target allocation requires review: '.$e->getMessage(),
                ]);
            });
            Log::warning('DNG receipt captured but allocation requires review', [
                'dng_payment_request_id' => $request->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->publishAllocationNotification($request, $payment, $allocations);

        Log::info('DNG payment bridged to canonical Payment', [
            'dng_payment_request_id' => $request->id,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);

        return $payment;
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
}
