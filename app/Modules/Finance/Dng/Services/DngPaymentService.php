<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Services;

use App\Models\Department;
use App\Models\Payment;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DngPaymentService
{
    public function __construct(
        protected DngClient $dngClient,
        protected PaymentService $paymentService,
        protected PublishDomainEventAction $publishDomainEventAction,
    ) {}

    /**
     * Create a local DNG payment request and push debt to DNG.
     *
     * @param  array{
     *     campus_code: string,
     *     student_code: string,
     *     fee_type: string,
     *     description?: string|null,
     *     semester_id?: int|null,
     *     due_date?: string|null,
     *     item_id: string,
     *     amount: float|int|string,
     *     type: string,
     *     student_name: string,
     *     email: string,
     *     estimate_time: string,
     *     student_address: string,
     *     cccd?: string|null,
     * }  $chargeData
     */
    public function createAndPush(Student $student, array $chargeData): DngPaymentRequest
    {
        $duplicate = DngPaymentRequest::query()
            ->where('student_id', $student->id)
            ->where('fee_type', $chargeData['fee_type'])
            ->where('amount', $chargeData['amount'])
            ->awaitingPayment()
            ->exists();

        if ($duplicate) {
            throw new \RuntimeException("DNG đang chờ với loại phí {$chargeData['fee_type']} và số tiền {$chargeData['amount']} đã tồn tại cho student này.");
        }

        $previousRequestIds = DngPaymentRequest::query()
            ->where('student_id', $student->id)
            ->where('fee_type', $chargeData['fee_type'])
            ->awaitingPayment()
            ->pluck('id')
            ->all();

        // Step 1: Create local record first (must commit before calling DNG)
        $request = DngPaymentRequest::create([
            'student_id' => $student->id,
            'campus_code' => $chargeData['campus_code'],
            'student_code' => $chargeData['student_code'],
            'fee_type' => $chargeData['fee_type'],
            'description' => $chargeData['description'] ?? null,
            'semester_id' => $chargeData['semester_id'] ?? null,
            'due_date' => $chargeData['due_date'] ?? null,
            'item_id' => $chargeData['item_id'],
            'amount' => $chargeData['amount'],
            'status' => DngPaymentRequest::STATUS_PENDING,
        ]);

        // Step 2: Push to DNG
        $pushPayload = $this->dngClient->buildInsertNewRecordPayload($chargeData);

        try {
            $response = $this->dngClient->insertNewRecord($chargeData, $pushPayload);

            DB::transaction(function () use ($request, $pushPayload, $response, $previousRequestIds): void {
                $request->update([
                    'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                    'push_payload' => $pushPayload,
                    'push_response' => $response,
                    'dng_transaction_id' => $response['data']['TransactionID'] ?? $response['data']['Id'] ?? null,
                    'dng_payment_id' => $response['data']['PaymentId'] ?? $response['data']['OtherId'] ?? null,
                ]);

                if ($previousRequestIds !== []) {
                    DngPaymentRequest::query()
                        ->whereIn('id', $previousRequestIds)
                        ->awaitingPayment()
                        ->update([
                            'status' => DngPaymentRequest::STATUS_CANCELLED,
                        ]);
                }
            });

            $this->publishPushNotification(
                studentId: (int) $student->id,
                campusId: (int) $student->campus_id,
                studentName: $student->full_name,
                requestId: $request->id,
                amount: (string) $chargeData['amount'],
                description: $chargeData['description'] ?? $chargeData['fee_type'],
            );
        } catch (\Throwable $e) {
            $request->update([
                'status' => DngPaymentRequest::STATUS_FAILED,
                'push_payload' => $pushPayload,
                'error_message' => $e->getMessage(),
            ]);

            Log::error('DNG push debt failed', [
                'dng_payment_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $request->refresh();
    }

    /**
     * Create local DNG payment request records and push them as a batch to DNG.
     *
     * @param  array<int, array{
     *     student_id: int,
     *     student_code: string,
     *     type: string,
     *     amount: float|int,
     *     semester_id?: int|null,
     *     due_date?: string|null,
     *     item_id: string,
     *     student_name: string,
     *     email: string,
     *     estimate_time: string,
     *     student_address: string,
     *     note?: string|null,
     *     note_einvoice?: string|null,
     *     cccd?: string|null,
     * }>  $records
     * @return array{created: int, skipped: int, failed: int}
     */
    public function createAndPushBatch(array $records, string $campusCode): array
    {
        // Step 1: Persist all local records before calling DNG
        $created = [];
        $skipped = 0;
        foreach ($records as $record) {
            $duplicate = DngPaymentRequest::query()
                ->where('student_id', $record['student_id'])
                ->where('fee_type', $record['type'])
                ->where('amount', $record['amount'])
                ->awaitingPayment()
                ->exists();

            if ($duplicate) {
                $skipped++;

                continue;
            }

            $previousRequestIds = DngPaymentRequest::query()
                ->where('student_id', $record['student_id'])
                ->where('fee_type', $record['type'])
                ->awaitingPayment()
                ->pluck('id')
                ->all();

            $request = DngPaymentRequest::create([
                'student_id' => $record['student_id'],
                'campus_code' => $campusCode,
                'student_code' => $record['student_code'],
                'fee_type' => $record['type'],
                'description' => $record['description'] ?? $record['note'] ?? null,
                'semester_id' => $record['semester_id'] ?? null,
                'due_date' => $record['due_date'] ?? null,
                'item_id' => $record['item_id'],
                'amount' => $record['amount'],
                'status' => DngPaymentRequest::STATUS_PENDING,
            ]);
            $created[] = ['request' => $request, 'data' => $record, 'previous_request_ids' => $previousRequestIds];
        }

        // Step 2: Build batch payload and push
        $batchData = array_map(fn ($r) => $r['data'], $created);
        $payload = $this->dngClient->buildBatchInsertPayload($campusCode, $batchData);

        try {
            $response = $this->dngClient->insertBatchRecords($campusCode, $batchData);

            $campusId = app()->bound('campus') ? (int) app('campus')->id : null;

            foreach ($created as $i => $item) {
                $responseRecord = $response['data'][$i] ?? null;
                $recordPayload = $payload['Records'][$i] ?? null;

                DB::transaction(function () use ($item, $recordPayload, $responseRecord): void {
                    $item['request']->update([
                        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                        'push_payload' => $recordPayload,
                        'push_response' => $responseRecord,
                        'dng_transaction_id' => $responseRecord['TransactionID'] ?? $responseRecord['Id'] ?? null,
                        'dng_payment_id' => $responseRecord['PaymentId'] ?? $responseRecord['OtherId'] ?? null,
                    ]);

                    if (($item['previous_request_ids'] ?? []) !== []) {
                        DngPaymentRequest::query()
                            ->whereIn('id', $item['previous_request_ids'])
                            ->awaitingPayment()
                            ->update([
                                'status' => DngPaymentRequest::STATUS_CANCELLED,
                            ]);
                    }
                });

                $this->publishPushNotification(
                    studentId: (int) $item['data']['student_id'],
                    campusId: $campusId,
                    studentName: $item['data']['student_name'],
                    requestId: $item['request']->id,
                    amount: (string) $item['data']['amount'],
                    description: $item['data']['note'] ?? $item['data']['type'],
                );
            }

            return ['created' => count($created), 'skipped' => $skipped, 'failed' => 0];
        } catch (\Throwable $e) {
            foreach ($created as $item) {
                $item['request']->update([
                    'status' => DngPaymentRequest::STATUS_FAILED,
                    'push_payload' => $payload,
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
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
    public function bridgeToPayment(DngPaymentRequest $request): ?Payment
    {
        if ($request->hasBridgedPayment()) {
            return $request->payment;
        }

        return DB::transaction(function () use ($request) {
            // Lock row to prevent concurrent bridge (webhook + reconciliation race)
            $request = DngPaymentRequest::lockForUpdate()->find($request->id);
            if ($request->hasBridgedPayment()) {
                return $request->payment;
            }

            $payment = $this->paymentService->recordPayment([
                'student_id' => $request->student_id,
                'amount' => $request->amount,
                'method' => Payment::METHOD_GATEWAY,
                'source' => 'dng',
                'external_ref' => $request->dng_payment_id,
                'paid_at' => $request->paid_at ?? now(),
                'status' => Payment::STATUS_COMPLETED,
                'raw_payload' => $request->last_callback_payload,
                'received_by_user_id' => null,
            ]);

            $request->update(['payment_id' => $payment->id]);

            // Auto-allocate the payment to outstanding charges
            $allocations = $this->paymentService->autoAllocatePayment($payment->id);

            $this->publishAllocationNotification($request, $payment, $allocations);

            Log::info('DNG payment bridged to canonical Payment', [
                'dng_payment_request_id' => $request->id,
                'payment_id' => $payment->id,
                'amount' => $request->amount,
            ]);

            return $payment;
        });
    }
}
