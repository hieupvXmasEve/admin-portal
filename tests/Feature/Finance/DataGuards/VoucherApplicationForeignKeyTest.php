<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Models\VoucherApplication;
use App\Models\VoucherDefinition;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

require_once __DIR__.'/guard_fixtures.php';

/**
 * DB-08 / FIN-14: voucher_applications.invoice_id and finance_charge_id are now
 * real foreign keys with ON DELETE SET NULL.
 */
function fkGuardVoucherDefinition(): VoucherDefinition
{
    return VoucherDefinition::query()->create([
        'code' => 'VC-FK-'.uniqid(),
        'name' => 'FK guard voucher',
        'description' => 'test',
        'voucher_type' => 'discount',
        'discount_type' => 'fixed_amount',
        'discount_value' => 1_000_000,
        'max_discount_amount' => 1_000_000,
        'is_stackable' => false,
        'max_uses_per_student' => 1,
        'valid_from' => now()->subDay()->toDateString(),
        'valid_until' => now()->addDay()->toDateString(),
        'is_active' => true,
    ]);
}

function fkGuardBaseRow(Student $student, Semester $semester, VoucherDefinition $voucher): array
{
    return [
        'voucher_definition_id' => $voucher->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'applied',
        'applied_at' => now(),
        'discount_amount' => 1_000_000,
    ];
}

it('rejects a voucher application pointing at a non-existent invoice', function () {
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();
    $voucher = fkGuardVoucherDefinition();

    expect(fn () => DB::table('voucher_applications')->insert(
        fkGuardBaseRow($student, $semester, $voucher) + ['invoice_id' => 999_999]
    ))->toThrow(QueryException::class);
});

it('rejects a voucher application pointing at a non-existent finance charge', function () {
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();
    $voucher = fkGuardVoucherDefinition();

    expect(fn () => DB::table('voucher_applications')->insert(
        fkGuardBaseRow($student, $semester, $voucher) + ['finance_charge_id' => 999_999]
    ))->toThrow(QueryException::class);
});

it('nulls invoice_id when the referenced invoice is deleted', function () {
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();
    $voucher = fkGuardVoucherDefinition();

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-FK-1',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $application = VoucherApplication::query()->create(
        fkGuardBaseRow($student, $semester, $voucher) + ['invoice_id' => $invoice->id]
    );

    $invoice->delete();

    expect($application->fresh()->invoice_id)->toBeNull();
});

it('nulls finance_charge_id when the referenced charge is deleted', function () {
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();
    $voucher = fkGuardVoucherDefinition();

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10_000_000,
        'description' => 'fk guard',
        'effective_at' => now(),
        'status' => 'active',
    ]);

    $application = VoucherApplication::query()->create(
        fkGuardBaseRow($student, $semester, $voucher) + ['finance_charge_id' => $charge->id]
    );

    $charge->delete();

    expect($application->fresh()->finance_charge_id)->toBeNull();
});
