<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected GetStudentBalanceQuery $getStudentBalanceQuery
    ) {}

    /**
     * Record a new payment.
     */
    public function recordPayment(array $data): Payment
    {
        return Payment::create([
            'student_id' => $data['student_id'],
            'amount' => $data['amount'],
            'method' => $data['method'] ?? Payment::METHOD_OTHER,
            'source' => $data['source'] ?? null,
            'external_ref' => $data['external_ref'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
            'status' => $data['status'] ?? Payment::STATUS_COMPLETED,
            'received_by_user_id' => $data['received_by_user_id'] ?? auth()->id(),
            'raw_payload' => $data['raw_payload'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Allocate a payment to specific charges.
     *
     * @param int $paymentId
     * @param array $allocations Array of ['charge_id' => amount]
     * @return Collection<PaymentAllocation>
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

                $allocation = PaymentAllocation::updateOrCreate(
                    [
                        'payment_id' => $payment->id,
                        'charge_id' => $chargeId,
                    ],
                    [
                        'allocated_amount' => $amount,
                        'allocated_at' => now(),
                        'allocated_by_user_id' => $userId ?? auth()->id(),
                    ]
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
        $payment = Payment::with('allocations')->findOrFail($paymentId);
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
            ->where('amount', '>', 0)
            ->with('allocations');

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
            ->with('allocations')
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
            ->with(['allocations.charge', 'receivedBy'])
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
}