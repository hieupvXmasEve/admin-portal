<?php

declare(strict_types=1);

use App\Models\FinanceCharge;
use App\Models\Semester;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

require_once __DIR__.'/guard_fixtures.php';

/**
 * DB-05: status CHECK constraints reject values outside the PHP value sets while
 * still accepting every legitimate status.
 */
function statusGuardCharge(): FinanceCharge
{
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();

    return FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10_000_000,
        'description' => 'status guard',
        'effective_at' => now(),
        'status' => 'active',
    ]);
}

function statusGuardDngRequest(): array
{
    $student = makeGuardStudent();

    return [
        'student_id' => $student->id,
        'campus_code' => 'AUH',
        'student_code' => 'SWB001',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 1_000_000,
    ];
}

it('rejects an invalid installment status', function () {
    $charge = statusGuardCharge();

    expect(fn () => DB::table('finance_charge_installments')->insert([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 1_000_000,
        'due_date' => now()->toDateString(),
        'status' => 'not_a_real_status',
    ]))->toThrow(QueryException::class);
});

it('accepts every valid installment status', function (string $status) {
    $charge = statusGuardCharge();

    DB::table('finance_charge_installments')->insert([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 1_000_000,
        'due_date' => now()->toDateString(),
        'status' => $status,
    ]);

    expect(DB::table('finance_charge_installments')->where('status', $status)->count())->toBe(1);
})->with(['pending', 'awaiting_payment', 'paid', 'cancelled']);

it('rejects an invalid dng payment request status', function () {
    expect(fn () => DB::table('dng_payment_requests')->insert(
        statusGuardDngRequest() + ['status' => 'bogus_status']
    ))->toThrow(QueryException::class);
});

it('accepts a valid dng payment request status', function () {
    DB::table('dng_payment_requests')->insert(
        statusGuardDngRequest() + ['status' => 'cancel_pushed_to_dng']
    );

    expect(DB::table('dng_payment_requests')->count())->toBe(1);
});

it('rejects an invalid webhook processing status', function () {
    expect(fn () => DB::table('dng_webhook_events')->insert([
        'event_type' => 'payment_invoiced',
        'payload_hash' => str_repeat('a', 64),
        'payload' => '{"x":1}',
        'is_valid_checksum' => 1,
        'processing_status' => 'invalid_proc',
    ]))->toThrow(QueryException::class);
});

it('accepts a valid webhook processing status', function () {
    DB::table('dng_webhook_events')->insert([
        'event_type' => 'payment_invoiced',
        'payload_hash' => str_repeat('b', 64),
        'payload' => '{"x":1}',
        'is_valid_checksum' => 1,
        'processing_status' => 'processed',
    ]);

    expect(DB::table('dng_webhook_events')->count())->toBe(1);
});
