<?php

declare(strict_types=1);

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/integrity_fixtures.php';

it('command reports all invariants passing on a clean dataset', function () {
    $this->artisan('finance:audit-invariants')
        ->expectsOutputToContain('All invariants pass')
        ->assertSuccessful();
});

it('command still detects an over-allocated payment after the registry refactor', function () {
    $student = auditStudent();
    seedOverAllocatedPayment($student);

    $this->artisan('finance:audit-invariants')
        ->expectsOutputToContain('INV-1')
        ->expectsOutputToContain('total offending')
        ->assertSuccessful();
});

it('command renders sample ids without error under --sample', function () {
    $student = auditStudent();
    seedOverAllocatedPayment($student);

    $this->artisan('finance:audit-invariants', ['--sample' => true])
        ->expectsOutputToContain('INV-1')
        ->assertSuccessful();
});

it('prints operational evidence for a live installment on a void charge', function () {
    $student = auditStudent();
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $student->intake_semester_id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1500,
        'description' => 'Voided installment evidence fixture',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_VOID,
    ]);
    $installment = FinanceChargeInstallment::query()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 1500,
        'due_date' => now()->addDay()->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_PENDING,
    ]);

    $this->artisan('finance:audit-invariants', ['--sample' => true])
        ->expectsOutputToContain($student->student_id)
        ->expectsOutputToContain('Raw ledger components')
        ->expectsOutputToContain('finance_invariant.INV-13.live_installment_on_void_charge')
        ->assertSuccessful();
});

it('prints operational evidence for an active invoice line on a void charge', function () {
    $student = auditStudent();
    $line = makeInvoiceLineForStudent($student);
    $line->charge()->update(['status' => FinanceCharge::STATUS_VOID]);

    $this->artisan('finance:audit-invariants', ['--sample' => true])
        ->expectsOutputToContain($student->student_id)
        ->expectsOutputToContain('Raw ledger components')
        ->expectsOutputToContain('finance_invariant.INV-18.active_invoice_line_on_void_charge')
        ->assertSuccessful();
});
