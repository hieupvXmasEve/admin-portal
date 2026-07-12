<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Http\Export\InvoiceExport;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Reporting\CurrentSettlementPositionPresenter;
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
        ->and($row[9])->toBe('partially_settled')
        ->and($row[11])->toBe('')
        ->and($row[12])->toContain('payable_line_id');
});
