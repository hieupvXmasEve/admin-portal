<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

require_once __DIR__.'/guard_fixtures.php';

/**
 * DB-06: money-sign CHECK constraints. Inserts go through DB::table so the test
 * proves the DB constraint itself, not a model-level guard.
 */
function signGuardCharge(string $type, float $amount): array
{
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();

    return [
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => $type,
        'amount' => $amount,
        'description' => 'guard test',
        'effective_at' => now(),
        'status' => 'active',
    ];
}

it('rejects a payment with zero or negative amount', function (float $amount) {
    $student = makeGuardStudent();

    expect(fn () => DB::table('payments')->insert([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => 'cash',
        'paid_at' => now(),
        'status' => 'completed',
    ]))->toThrow(QueryException::class);
})->with([0.0, -1000.0]);

it('accepts a positive payment amount', function () {
    $student = makeGuardStudent();

    DB::table('payments')->insert([
        'student_id' => $student->id,
        'amount' => 1000,
        'method' => 'cash',
        'paid_at' => now(),
        'status' => 'completed',
    ]);

    expect(DB::table('payments')->count())->toBe(1);
});

it('rejects an installment with non-positive amount', function () {
    $charge = FinanceCharge::create(signGuardCharge(FinanceCharge::TYPE_TUITION_TERM, 10_000_000));

    expect(fn () => DB::table('finance_charge_installments')->insert([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 0,
        'due_date' => now()->toDateString(),
        'status' => 'pending',
    ]))->toThrow(QueryException::class);
});

it('rejects each credit type stored with a positive amount (sign flip)', function (string $type) {
    expect(fn () => DB::table('finance_charges')->insert(
        signGuardCharge($type, 5_000_000)
    ))->toThrow(QueryException::class);
})->with([
    FinanceEntitlementType::ScholarshipCredit,
    FinanceEntitlementType::VoucherCredit,
    FinanceEntitlementType::DeferCredit,
    FinanceEntitlementType::EgcExemptCredit,
]);

it('rejects each debit type stored with a negative amount (sign flip)', function (string $type) {
    expect(fn () => DB::table('finance_charges')->insert(
        signGuardCharge($type, -5_000_000)
    ))->toThrow(QueryException::class);
})->with([
    FinanceCharge::TYPE_TUITION_TERM,
    FinanceCharge::TYPE_EGC_LEVEL_FEE,
    FinanceCharge::TYPE_BHYT,
]);

it('accepts correctly-signed charges including zero (debit >= 0, credit <= 0, adjustment any)', function (string $type, float $amount) {
    DB::table('finance_charges')->insert(signGuardCharge($type, $amount));

    expect(DB::table('finance_charges')->count())->toBe(1);
})->with([
    [FinanceCharge::TYPE_TUITION_TERM, 15_000_000.0],
    [FinanceCharge::TYPE_TUITION_TERM, 0.0],             // zero-debt invoice — allowed
    [FinanceEntitlementType::ScholarshipCredit, -4_500_000.0],
    [FinanceEntitlementType::ScholarshipCredit, 0.0],       // zero credit — allowed
    [FinanceCharge::TYPE_ADJUSTMENT, -123.0],            // adjustment unconstrained
    [FinanceCharge::TYPE_ADJUSTMENT, 123.0],
]);

it('keeps a correctly-signed historical void credit row readable', function () {
    $row = signGuardCharge(FinanceEntitlementType::DeferCredit, -125_000);
    $row['status'] = FinanceCharge::STATUS_VOID;

    DB::table('finance_charges')->insert($row);

    expect(DB::table('finance_charges')
        ->where('charge_type', FinanceEntitlementType::DeferCredit)
        ->where('status', FinanceCharge::STATUS_VOID)
        ->value('amount'))->toBe('-125000.00');
});
