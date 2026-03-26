<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Student;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Notification\Actions\PublishDomainEventAction;
use App\Modules\Notification\Domain\Contracts\DomainEventEnvelope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected GetStudentBalanceQuery $getStudentBalanceQuery,
        protected PublishDomainEventAction $publishDomainEventAction,
        protected SettlementService $settlementService,
    ) {}

    /**
     * Record a new payment.
     */
    public function recordPayment(array $data): Payment
    {
        $payment = Payment::create([
            'student_id' => $data['student_id'],
            'amount' => $data['amount'],
            'method' => $data['method'] ?? Payment::METHOD_OTHER,
            'source' => $data['source'] ?? null,
            'external_ref' => $data['external_ref'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'status' => $data['status'] ?? Payment::STATUS_COMPLETED,
            'received_by_user_id' => $data['received_by_user_id'] ?? $this->currentUserId(),
            'raw_payload' => $data['raw_payload'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->publishInvoicePaidDomainEvent($payment);

        return $payment;
    }

    protected function publishInvoicePaidDomainEvent(Payment $payment): void
    {
        if (! (bool) config('notification.v2_enabled', false)) {
            return;
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2_only'], true)) {
            return;
        }

        $student = Student::query()->find($payment->student_id);
        if (! $student) {
            return;
        }

        $envelope = new DomainEventEnvelope(
            eventId: (string) Str::uuid(),
            eventName: 'finance.invoice_paid',
            eventVersion: 1,
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'payment',
            aggregateId: (string) $payment->id,
            campusId: (int) $student->campus_id,
            actorUserId: $this->currentUserId(),
            payload: [
                'type_key' => 'invoice_paid',
                'student_id' => (int) $student->id,
                'channels' => ['email', 'realtime'],
                'recipient_targets' => [
                    ['type' => 'student', 'id' => (int) $student->id],
                ],
                'data' => [
                    'title' => 'Payment received',
                    'body' => 'Your payment has been recorded successfully.',
                    'payment_id' => (int) $payment->id,
                    'amount' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'method' => (string) $payment->method,
                ],
            ],
        );

        $this->publishDomainEventAction->runAfterCommit($envelope);
    }

    /**
     * Allocate a payment to specific charges.
     *
     * @param  array  $allocations  Array of ['charge_id' => amount]
     * @return Collection<PaymentApplication>
     */
    public function allocatePayment(int $paymentId, array $allocations, ?int $userId = null): Collection
    {
        $payment = Payment::findOrFail($paymentId);
        $createdAllocations = collect();

        DB::transaction(function () use ($payment, $allocations, $userId, &$createdAllocations) {
            foreach ($allocations as $chargeId => $amount) {
                if ($amount <= 0) {
                    continue;
                }

                $line = InvoiceLine::query()
                    ->where('charge_id', (int) $chargeId)
                    ->where('status', 'active')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->first();

                if (! $line) {
                    continue;
                }

                $allocation = $this->settlementService->createPaymentApplication(
                    $payment,
                    $line,
                    (float) $amount,
                    'application',
                    $userId ?? $this->currentUserId(),
                    self::class,
                    null,
                );

                $createdAllocations->push($allocation);
            }
        });

        return $createdAllocations;
    }

    /**
     * Auto-allocate a payment to outstanding charges using oldest first strategy.
     */
    public function autoAllocatePayment(int $paymentId, string $strategy = 'oldest_first'): Collection
    {
        $payment = Payment::query()->findOrFail($paymentId);
        $unappliedAmount = $payment->unapplied_amount;

        if ($unappliedAmount <= 0) {
            return collect();
        }

        // Get outstanding charges (positive amount, with remaining balance)
        $charges = $this->getOutstandingCharges($payment->student_id)
            ->sortBy(function ($charge) use ($strategy) {
                return $strategy === 'oldest_first'
                    ? $charge->effective_at
                    : -$charge->effective_at->timestamp;
            });

        $allocations = [];

        foreach ($charges as $charge) {
            if ($unappliedAmount <= 0) {
                break;
            }

            $chargeBalance = $charge->balance;
            if ($chargeBalance <= 0) {
                continue;
            }

            $amountToAllocate = min($unappliedAmount, $chargeBalance);
            $allocations[$charge->id] = $amountToAllocate;
            $unappliedAmount -= $amountToAllocate;
        }

        return $this->allocatePayment($paymentId, $allocations);
    }

    /**
     * Get outstanding charges for a student (charges with remaining balance).
     */
    public function getOutstandingCharges(int $studentId, ?int $semesterId = null): Collection
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->get()->filter(function ($charge) {
            return $charge->balance > 0;
        });
    }

    /**
     * Get student balance summary.
     */
    public function getStudentBalance(int $studentId, ?int $semesterId = null): array
    {
        return $this->getStudentBalanceQuery->handle($studentId, $semesterId);
    }

    /**
     * Get unapplied credits (payment amounts not yet allocated).
     */
    public function getUnappliedCredits(int $studentId): float
    {
        $payments = Payment::where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->get();

        return $payments->sum(function ($payment) {
            return max(0, $payment->unapplied_amount);
        });
    }

    /**
     * Determine balance status.
     */
    protected function determineBalanceStatus(float $balance): string
    {
        if ($balance === 0.0) {
            return 'paid';
        } elseif ($balance > 0) {
            return 'outstanding';
        } else {
            return 'overpaid';
        }
    }

    /**
     * Get payment history for a student.
     */
    public function getPaymentHistory(int $studentId): Collection
    {
        return Payment::where('student_id', $studentId)
            ->with(['applications.invoiceLine.charge', 'receivedBy'])
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    /**
     * Check if a student has fully paid for a semester.
     */
    public function isFullyPaid(int $studentId, int $semesterId): bool
    {
        $balance = $this->getStudentBalance($studentId, $semesterId);

        return $balance['balance'] <= 0;
    }

    protected function currentUserId(): ?int
    {
        $actor = Auth::user();
        if (! $actor) {
            return null;
        }

        return (int) $actor->getAuthIdentifier();
    }
}
