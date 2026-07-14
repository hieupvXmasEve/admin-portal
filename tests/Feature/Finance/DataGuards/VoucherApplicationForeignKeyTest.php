<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Models\VoucherApplication;
use App\Models\VoucherDefinition;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

require_once __DIR__.'/guard_fixtures.php';

/**
 * voucher_applications keeps its invoice FK, but no longer persists a direct
 * Finance charge pointer. Finance owns the historical voucher source identity.
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

it('does not retain a direct finance charge pointer', function () {
    expect(Schema::hasColumn('voucher_applications', 'finance_charge_id'))->toBeFalse();
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
