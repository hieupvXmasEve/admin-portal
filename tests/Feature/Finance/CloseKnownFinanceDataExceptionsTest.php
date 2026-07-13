<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('closes only the approved legacy finance exceptions and is safe to rerun', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['id' => 8]);
    $legitimate = Student::factory()->forCampus($campus)->create(['id' => 610, 'student_id' => 'AUH113129']);
    $fixture = Student::factory()->forCampus($campus)->create(['id' => 675, 'student_id' => 'STU69150356', 'user_id' => null]);

    $void = FinanceCharge::query()->create(['id' => 1176, 'student_id' => $legitimate->id, 'semester_id' => $semester->id, 'charge_type' => FinanceCharge::TYPE_RETAKE_FEE, 'amount' => 3_000_000, 'description' => 'historical void', 'effective_at' => now(), 'status' => FinanceCharge::STATUS_VOID, 'voided_at' => now(), 'void_reason' => 'historical']);
    $active = FinanceCharge::query()->create(['id' => 1184, 'student_id' => $legitimate->id, 'semester_id' => $semester->id, 'charge_type' => FinanceCharge::TYPE_RETAKE_FEE, 'amount' => 3_000_000, 'description' => 'legitimate retake', 'effective_at' => now(), 'status' => FinanceCharge::STATUS_ACTIVE]);
    $fixtureCharge = FinanceCharge::query()->create(['id' => 1188, 'student_id' => $fixture->id, 'semester_id' => 8, 'charge_type' => FinanceCharge::TYPE_RETAKE_FEE, 'amount' => 300_000, 'description' => 'fixture', 'effective_at' => now(), 'status' => FinanceCharge::STATUS_ACTIVE]);
    BillingAccount::query()->create(['id' => 236, 'student_id' => $fixture->id]);
    $invoice = StudentInvoice::query()->create(['id' => 1471, 'invoice_number' => 'X675', 'student_id' => $fixture->id, 'semester_id' => 8, 'status' => 'draft', 'due_date' => now(), 'subtotal' => 300_000, 'discount_total' => 0, 'total_amount' => 300_000, 'paid_amount' => 0]);
    $line = InvoiceLine::query()->create(['id' => 1203, 'invoice_id' => $invoice->id, 'charge_id' => $fixtureCharge->id, 'amount_snapshot' => 300_000, 'description_snapshot' => 'fixture', 'status' => 'active']);
    $payment = Payment::query()->create(['id' => 526, 'student_id' => $fixture->id, 'amount' => 100_000, 'method' => Payment::METHOD_CASH, 'source' => 'fixture', 'paid_at' => now(), 'status' => Payment::STATUS_COMPLETED]);
    PaymentApplication::query()->create(['id' => 774, 'payment_id' => $payment->id, 'invoice_line_id' => $line->id, 'amount' => 100_000, 'entry_type' => 'application', 'applied_at' => now()]);

    $this->artisan('finance:close-known-legacy-data-exceptions')->assertSuccessful();

    $obligation = FinanceObligation::query()->firstOrFail();
    expect($active->fresh()->finance_obligation_id)->toBe($obligation->id)
        ->and($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $obligation->amount)->toBe(3_000_000.0)
        ->and($void->fresh()->only(['status', 'voided_at', 'void_reason']))->toBe($void->only(['status', 'voided_at', 'void_reason']))
        ->and(Student::query()->find(675))->toBeNull()
        ->and(BillingAccount::query()->find(236))->toBeNull()
        ->and(FinanceCharge::query()->find(1188))->toBeNull()
        ->and(StudentInvoice::query()->find(1471))->toBeNull()
        ->and(InvoiceLine::query()->find(1203))->toBeNull()
        ->and(Payment::query()->find(526))->toBeNull()
        ->and(PaymentApplication::query()->find(774))->toBeNull();

    $this->artisan('finance:close-known-legacy-data-exceptions')->assertSuccessful();
    expect(FinanceObligation::query()->count())->toBe(1);
});
