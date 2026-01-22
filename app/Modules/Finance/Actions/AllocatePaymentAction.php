<?php

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocatePaymentAction
{
    public function run(Payment $payment, FinanceCharge $charge, float $amount, int $userId): PaymentAllocation
    {
        // 1. Validation
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Allocation amount must be positive.']);
        }

        if ($amount > $payment->unapplied_amount) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds unallocated payment balance.']);
        }

        if ($amount > $charge->balance) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds charge remaining balance.']);
        }

        return DB::transaction(function () use ($payment, $charge, $amount, $userId) {
            // Check if allocation already exists
            $allocation = PaymentAllocation::where('payment_id', $payment->id)
                ->where('charge_id', $charge->id)
                ->first();

            if ($allocation) {
                $allocation->update([
                    'allocated_amount' => $allocation->allocated_amount + $amount,
                    'allocated_by_user_id' => $userId,
                    'allocated_at' => now(),
                ]);
            } else {
                $allocation = PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'charge_id' => $charge->id,
                    'allocated_amount' => $amount,
                    'allocated_at' => now(),
                    'allocated_by_user_id' => $userId,
                ]);
            }

            return $allocation;
        });
    }
}
