<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceChargeInstallment>
 *
 * Note: FinanceCharge has no factory in this codebase. Callers MUST pass
 * `finance_charge_id` explicitly via ->create(['finance_charge_id' => $id]).
 */
class FinanceChargeInstallmentFactory extends Factory
{
    protected $model = FinanceChargeInstallment::class;

    public function definition(): array
    {
        return [
            // finance_charge_id intentionally omitted — caller must provide.
            'installment_no' => 1,
            'amount' => fake()->randomFloat(2, 100_000, 5_000_000),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => FinanceChargeInstallment::STATUS_PENDING,
            'dng_payment_request_id' => null,
            'paid_at' => null,
            'push_attempt_count' => 0,
            'last_push_error' => null,
            'last_push_attempted_at' => null,
        ];
    }

    public function awaitingPayment(): self
    {
        return $this->state(fn () => [
            'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        ]);
    }

    public function paid(): self
    {
        return $this->state(fn () => [
            'status' => FinanceChargeInstallment::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn () => [
            'status' => FinanceChargeInstallment::STATUS_CANCELLED,
        ]);
    }

    public function pushFailed(string $error = 'DNG HTTP 500'): self
    {
        return $this->state(fn () => [
            'status' => FinanceChargeInstallment::STATUS_PENDING,
            'push_attempt_count' => 3,
            'last_push_error' => $error,
            'last_push_attempted_at' => now(),
        ]);
    }
}
