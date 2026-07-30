<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Http\Export\InvoiceExport;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Reporting\CurrentSettlementPositionPresenter;
use App\Modules\Finance\Support\Reporting\SettlementReportAsOfContext;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports current invoice rows from one canonical position instead of paid cache', function (): void {
    $user = User::factory()->create();
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $account = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-EXPORT-CURRENT',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'cached_total_amount' => 999_999,
        'cached_paid_amount' => 888_888,
    ]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'test',
        'source_kind' => 'current-report-export',
        'source_ref' => 'export:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 1_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'current-report-export',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1_000_000,
        'description' => 'Current export charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1_000_000,
        'description_snapshot' => 'Current export line',
        'status' => 'active',
    ]);
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 250_000,
        'method' => Payment::METHOD_CASH,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 250_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $export = new InvoiceExport(
        ['semester_id' => $semester->id],
        app(SettlementPositionReader::class),
        app(CurrentSettlementPositionPresenter::class),
    );
    $invoice->load(['student', 'semester']);
    $export->prepareRows([$invoice]);
    $row = $export->map($invoice);

    expect($row[4])->toBe('1000000.00')
        ->and($row[5])->toBe('0.00')
        ->and($row[6])->toBe('250000.00')
        ->and($row[7])->toBe('0.00')
        ->and($row[8])->toBe('750000.00')
        ->and($row[9])->toBe('')
        ->and($row[10])->toBe(Payment::METHOD_CASH)
        ->and($row[11])->toBe('partially_settled')
        ->and($row[13])->toBe('')
        ->and($row[14])->toContain('payable_line_id');
});

it('exports historical invoice rows with the requested as-of contract', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $account = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $asOf = '2026-07-01T12:00:00+07:00';
    $effectiveAt = '2026-06-30 12:00:00';
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-EXPORT-HISTORICAL',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => '2026-06-01',
    ]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'test',
        'source_kind' => 'historical-report-export',
        'source_ref' => 'historical-export:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => '1000000.00',
        'currency' => 'VND',
        'pricing_rule_version' => 'historical-report-export',
        'pricing_snapshot' => [],
        'accepted_at' => $effectiveAt,
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => '1000000.00',
        'description' => 'Historical export charge',
        'effective_at' => $effectiveAt,
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => '1000000.00',
        'description_snapshot' => 'Historical export line',
        'status' => 'active',
    ]);
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => '250000.00',
        'method' => Payment::METHOD_CASH,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => '2026-07-02 12:00:00',
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => '250000.00',
        'entry_type' => 'application',
        'applied_at' => '2026-07-02 12:00:00',
    ]);

    $export = new InvoiceExport(
        ['semester_id' => $semester->id],
        app(SettlementPositionReader::class),
        app(CurrentSettlementPositionPresenter::class),
        SettlementReportAsOfContext::fromInput($asOf, 'Asia/Ho_Chi_Minh'),
    );
    $invoice->load(['student', 'semester']);
    $export->prepareRows([$invoice]);
    $row = $export->map($invoice);

    expect($row[6])->toBe('0.00')
        ->and($row[8])->toBe('1000000.00')
        ->and($row[10])->toBe(Payment::METHOD_CASH)
        ->and($row[16])->toBe('as_of')
        ->and($row[17])->toContain('2026-07-01T12:00:00')
        ->and($row[18])->toBe('Asia/Ho_Chi_Minh')
        ->and($row[19])->toContain('finance_charges.effective_at');
});

it('aggregates distinct DNG payment refs and payment methods across an invoice settled by multiple payments', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $account = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-EXPORT-DNG',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'test',
        'source_kind' => 'dng-export',
        'source_ref' => 'dng-export:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => '1000000.00',
        'currency' => 'VND',
        'pricing_rule_version' => 'dng-export',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1_000_000,
        'description' => 'DNG export charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 1_000_000,
        'description_snapshot' => 'DNG export line',
        'status' => 'active',
    ]);

    $cashPayment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 400_000,
        'method' => Payment::METHOD_CASH,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $cashPayment->id,
        'invoice_line_id' => $line->id,
        'amount' => 400_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $gatewayPayment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 600_000,
        'method' => Payment::METHOD_GATEWAY,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $gatewayPayment->id,
        'invoice_line_id' => $line->id,
        'amount' => 600_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);
    DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => $campus->code ?? 'SWB',
        'student_code' => 'SWB-DNG-001',
        'fee_type' => 'tuition',
        'item_id' => 'item-'.uniqid('', true),
        'amount' => 600_000,
        'status' => DngPaymentRequest::STATUS_RECONCILED,
        'dng_payment_id' => 'DNG-REF-001',
        'payment_id' => $gatewayPayment->id,
    ]);

    $export = new InvoiceExport(
        ['semester_id' => $semester->id],
        app(SettlementPositionReader::class),
        app(CurrentSettlementPositionPresenter::class),
    );
    $invoice->load(['student', 'semester']);
    $export->prepareRows([$invoice]);
    $row = $export->map($invoice);

    expect($row[9])->toBe('DNG-REF-001')
        ->and($row[10])->toBe(Payment::METHOD_CASH.','.Payment::METHOD_GATEWAY);
});
