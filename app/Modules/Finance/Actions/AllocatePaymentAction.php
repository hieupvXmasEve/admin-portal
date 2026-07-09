<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Services\SettlementService;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AllocatePaymentAction
{
    public function __construct(
        protected SettlementService $settlementService
    ) {}

    public function run(Payment $payment, FinanceCharge $charge, float $amount, ?int $userId = null): PaymentApplication
    {
        // 1. Validation
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Allocation amount must be positive.']);
        }

        if ($amount > $payment->unapplied_amount) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds unallocated payment balance.']);
        }

        $line = InvoiceLine::query()
            ->where('charge_id', $charge->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if (! $line) {
            throw ValidationException::withMessages(['charge_id' => 'Charge does not have an active invoice line.']);
        }

        if ($payment->student_id !== $line->invoice?->student_id) {
            throw ValidationException::withMessages(['charge_id' => 'Payment student does not match invoice student.']);
        }

        if ($amount > $this->settlementService->getLineOutstandingAmount($line)) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds charge remaining balance.']);
        }

        // The checks above are a fast-fail UX guard. The authoritative check
        // runs inside the transaction under a row lock so a concurrent allocator
        // (e.g. the DNG webhook bridge) cannot race this one into over-allocation.
        $application = DB::transaction(function () use ($payment, $line, $amount, $userId) {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $lockedLine = InvoiceLine::query()->lockForUpdate()->findOrFail($line->id);

            if ($amount > $this->settlementService->getPaymentUnappliedAmount($lockedPayment)) {
                throw ValidationException::withMessages(['amount' => 'Amount exceeds unallocated payment balance.']);
            }

            if ($amount > $this->settlementService->getLineOutstandingAmount($lockedLine)) {
                throw ValidationException::withMessages(['amount' => 'Amount exceeds charge remaining balance.']);
            }

            return $this->settlementService->createPaymentApplication(
                $lockedPayment,
                $lockedLine,
                $amount,
                'application',
                $userId,
                self::class,
                null,
            );
        });

        app(RetakeRegistrationPaymentSyncer::class)->runForChargeIds([$charge->id]);
        app(ExamResitAttemptPaymentSyncer::class)->runForChargeIds([$charge->id]);

        return $application;
    }
}
