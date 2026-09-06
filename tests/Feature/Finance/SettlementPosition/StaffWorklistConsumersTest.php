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
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function makeStaffWorklistLine(Campus $campus, Semester $semester, string $amount = '10000000.00', string $lineAmount = '10000000.00'): InvoiceLine
{
    $student = Student::factory()->forCampus($campus)->create([
        'student_id' => 'STAFF-'.uniqid(),
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'status' => 'intake_course',
    ]);
    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-STAFF-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
    ]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'staff_worklist',
        'source_ref' => 'staff-worklist:'.uniqid(),
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'staff-worklist-test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => $amount,
        'description' => 'Staff worklist test charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $lineAmount,
        'description_snapshot' => 'Staff worklist test line',
        'status' => 'active',
    ]);
}

it('uses one canonical position for staff settlement rows and keeps cash separate', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $line = makeStaffWorklistLine($campus, $semester);
    $studentId = (int) $line->invoice()->value('student_id');
    $payment = Payment::query()->create([
        'student_id' => $studentId,
        'amount' => '3000000.00',
        'method' => Payment::METHOD_CASH,
        'source' => 'staff-worklist-test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => '3000000.00',
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    app()->singleton('campus', fn () => $campus);
    $result = app(ListSettlementWorklistQuery::class)->handle(Request::create('/finance/operations/settlement', 'GET'));
    $row = collect($result['students']->items())->first();

    expect($row['gross'])->toBe(10000000.0)
        ->and($row['cash'])->toBe(3000000.0)
        ->and($row['credit'])->toBe(0.0)
        ->and($row['active_due'])->toBe(7000000.0)
        ->and($row['settlement_label'])->toBe('Còn phải thu')
        ->and($row['money_item_status']['code'])->toBe('awaiting_payment')
        ->and($row['actionable'])->toBeFalse();
});

it('routes an invalid DNG position to exceptions without a push amount', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    makeStaffWorklistLine($campus, $semester, '10000000.00', '0.00');

    app()->singleton('campus', fn () => $campus);
    $result = app(ListDngWorklistQuery::class)->handle(Request::create('/finance/operations/dng-worklist', 'GET', [
        'dng_fee_type' => 'HL',
    ]));

    expect($result['students']->total())->toBe(0)
        ->and($result['summary']['needs_review_students'])->toBe(1)
        ->and($result['exceptions'][0]['settlement_label'])->toBe('Cần kiểm tra')
        ->and($result['exceptions'][0]['next_push_amount'] ?? 0)->toBe(0);
});
