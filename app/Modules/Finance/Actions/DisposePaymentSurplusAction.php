<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Queries\Operations\ListUnresolvedSurplusQuery;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\Reporting\UnappliedCashReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisposePaymentSurplusAction
{
    public function __construct(
        private readonly SettlementService $settlement,
        private readonly ProgramEnrollmentReader $programEnrollments,
        private readonly UnappliedCashReader $unappliedCash,
    ) {}

    /** @param array<string,mixed> $data */
    public function run(Payment $payment, array $data, int $operatorId): PaymentSurplusDisposition
    {
        if ($data['type'] === PaymentSurplusDisposition::TYPE_REFUND) {
            throw ValidationException::withMessages([
                'type' => [PaymentSurplusDisposition::REFUND_BLOCKED_MESSAGE],
            ]);
        }

        $existing = PaymentSurplusDisposition::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing !== null) {
            if ((int) $existing->payment_id !== (int) $payment->id) {
                throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key already belongs to another payment.']);
            }

            return $existing;
        }

        return DB::transaction(function () use ($payment, $data, $operatorId): PaymentSurplusDisposition {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $amount = round((float) $data['amount'], 2);
            $consumed = (float) PaymentSurplusDisposition::query()
                ->where('payment_id', $lockedPayment->id)
                ->whereIn('type', [PaymentSurplusDisposition::TYPE_REFUND, PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT])
                ->sum('amount');
            $available = round(max(0, $this->settlement->getPaymentUnappliedAmount($lockedPayment) - $consumed), 2);

            if ($amount <= 0 || $amount > $available) {
                throw ValidationException::withMessages(['amount' => 'Amount exceeds the current surplus balance.']);
            }

            $approvedBy = $operatorId;
            if ($data['type'] === PaymentSurplusDisposition::TYPE_RETAIN_FORFEIT) {
                $approvedBy = (int) $data['approved_by'];
                if ($approvedBy === $operatorId) {
                    throw ValidationException::withMessages([
                        'approved_by' => 'Người phê duyệt phải khác người thao tác.',
                    ]);
                }
                $this->assertRetainForfeitQueue((int) $lockedPayment->student_id);
            }

            $application = null;
            if ($data['type'] === PaymentSurplusDisposition::TYPE_REALLOCATE) {
                $line = InvoiceLine::query()->lockForUpdate()->findOrFail((int) $data['invoice_line_id']);
                if ((int) $line->invoice?->student_id !== (int) $lockedPayment->student_id || $line->status !== 'active') {
                    throw ValidationException::withMessages(['invoice_line_id' => 'Target is not an eligible active obligation.']);
                }
                if (DngPaymentRequestReservationTarget::query()->where('invoice_line_id', $line->id)
                    ->whereHas('dngPaymentRequest', fn ($query) => $query->holdingCollection())->exists()) {
                    throw ValidationException::withMessages(['invoice_line_id' => 'Target is held by an active collection request.']);
                }
                if ($amount > $this->settlement->getLineOutstandingAmount($line)) {
                    throw ValidationException::withMessages(['amount' => 'Amount exceeds the target collectible balance.']);
                }
                $application = $this->settlement->createPaymentApplication(
                    $lockedPayment, $line, $amount, 'application', $operatorId, self::class, null,
                );
            }

            $disposedAt = now();
            $evidence = array_filter([
                'payment_id' => (int) $lockedPayment->id,
                'type' => (string) $data['type'],
                'amount' => number_format($amount, 2, '.', ''),
                'invoice_line_id' => isset($data['invoice_line_id']) ? (int) $data['invoice_line_id'] : null,
                'external_reference' => $data['external_reference'] ?? null,
                'policy_code' => $data['policy_code'] ?? null,
                'reason' => $data['reason'] ?? null,
                'created_by_user_id' => $operatorId,
                'approved_by' => $approvedBy,
                'disposed_at' => $disposedAt->toISOString(),
            ], fn ($value) => $value !== null);
            $signature = hash_hmac('sha256', json_encode($evidence, JSON_THROW_ON_ERROR), (string) config('app.key'));

            return PaymentSurplusDisposition::query()->create([
                'payment_id' => $lockedPayment->id,
                'idempotency_key' => $data['idempotency_key'],
                'type' => $data['type'],
                'amount' => $amount,
                'payment_application_id' => $application?->id,
                'external_reference' => $data['external_reference'] ?? null,
                'policy_code' => $data['policy_code'] ?? null,
                'reason' => $data['reason'] ?? null,
                'evidence' => $evidence,
                'audit_signature' => $signature,
                'approved_by' => $approvedBy,
                'disposed_at' => $disposedAt,
            ]);
        });
    }

    private function assertRetainForfeitQueue(int $studentId): void
    {
        $unapplied = $this->unappliedCash->unappliedByStudent([$studentId])[$studentId] ?? 0.0;
        $status = $this->programEnrollments->forStudentId($studentId)->legacyCompatibleStatus();

        if ($unapplied <= 0 || ! ListUnresolvedSurplusQuery::isLeftSchool($status)) {
            throw ValidationException::withMessages([
                'type' => 'Chỉ giữ lại số dư khi sinh viên đã rời trường và còn dư cần quyết.',
            ]);
        }
    }
}
