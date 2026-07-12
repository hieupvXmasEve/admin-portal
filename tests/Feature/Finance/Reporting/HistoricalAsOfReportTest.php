<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();

    session([
        'current_campus_id' => $this->campus->id,
        'current_semester_id' => $this->semester->id,
    ]);
});

/** @return array{invoice: StudentInvoice, line: InvoiceLine, charge: FinanceCharge} */
function historicalAsOfLine(object $context, CarbonImmutable $effectiveAt, string $dueDate): array
{
    $student = Student::factory()->forCampus($context->campus)->create([
        'student_id' => 'HIST-'.uniqid(),
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $context->semester->id,
    ]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-HIST-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $context->semester->id,
        'status' => 'pending',
        'due_date' => $dueDate,
    ]);
    $account = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'academic',
        'source_kind' => 'historical_as_of_report_test',
        'source_ref' => 'historical:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => '1000000.00',
        'currency' => 'VND',
        'pricing_rule_version' => 'historical_as_of_report_test',
        'pricing_snapshot' => [],
        'accepted_at' => $effectiveAt,
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $context->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => '1000000.00',
        'description' => 'Historical report charge',
        'effective_at' => $effectiveAt,
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => '1000000.00',
        'description_snapshot' => 'Historical report line',
        'status' => 'active',
    ]);

    return ['invoice' => $invoice, 'line' => $line, 'charge' => $charge];
}

function historicalAsOfRows(TestResponse $response): array
{
    return $response->original->getData()['page']['props']['collection_progress']['rows']['data'];
}

it('uses one as-of position for historical collection and aging while excluding later payment evidence', function (): void {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $asOf = CarbonImmutable::parse('2026-07-01 12:00:00', 'Asia/Ho_Chi_Minh');
    $lineData = historicalAsOfLine($this, $asOf->subDay(), $asOf->subDays(31)->toDateString());
    $payment = Payment::query()->create([
        'student_id' => $lineData['invoice']->student_id,
        'amount' => '300000.00',
        'method' => Payment::METHOD_CASH,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => $asOf->addHour(),
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $lineData['line']->id,
        'amount' => '300000.00',
        'entry_type' => 'application',
        'applied_at' => $asOf->addHour(),
    ]);

    $asOfResponse = $this->actingAs($this->user)->get(route('finance.reporting.index', [
        'view' => 'historical-as-of',
        'as_of' => $asOf->toIso8601String(),
        'as_of_timezone' => 'Asia/Ho_Chi_Minh',
    ]))->assertOk();
    $currentResponse = $this->actingAs($this->user)->get(route('finance.reporting.index', [
        'view' => 'collection-progress',
        'search' => 'HIST-',
    ]))->assertOk();

    $asOfRow = historicalAsOfRows($asOfResponse)[0];
    $currentRow = historicalAsOfRows($currentResponse)[0];

    expect($asOfResponse->original->getData()['page']['props']['active_view'])->toBe('historical-as-of')
        ->and($asOfResponse->original->getData()['page']['props']['collection_progress']['meta']['position_mode'])->toBe('as_of')
        ->and($asOfResponse->original->getData()['page']['props']['collection_progress']['meta']['as_of_timezone'])->toBe('Asia/Ho_Chi_Minh')
        ->and($asOfRow['valid'])->toBeTrue()
        ->and($asOfRow['paid'])->toBe(0.0)
        ->and($asOfRow['outstanding'])->toBe(1_000_000.0)
        ->and($asOfRow['aging_bucket'])->toBe('d_31_60')
        ->and($currentRow['paid'])->toBe(300_000.0)
        ->and($currentRow['outstanding'])->toBe(700_000.0);
});

it('keeps a later void out of the earlier snapshot and exposes the report inventory', function (): void {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $asOf = CarbonImmutable::parse('2026-07-01 12:00:00', 'Asia/Ho_Chi_Minh');
    $lineData = historicalAsOfLine($this, $asOf->subDay(), $asOf->subDays(2)->toDateString());
    $lineData['invoice']->update(['status' => 'cancelled']);
    $lineData['line']->update(['status' => 'void', 'voided_at' => $asOf->addHour()]);
    $lineData['charge']->update(['status' => FinanceCharge::STATUS_VOID, 'voided_at' => $asOf->addHour()]);

    $response = $this->actingAs($this->user)->get(route('finance.reporting.index', [
        'view' => 'historical-as-of',
        'as_of' => $asOf->toIso8601String(),
        'as_of_timezone' => 'Asia/Ho_Chi_Minh',
    ]))->assertOk();

    $props = $response->original->getData()['page']['props'];
    $row = historicalAsOfRows($response)[0];
    $inventory = $props['collection_progress']['meta']['consumer_inventory'];

    expect($row['valid'])->toBeTrue()
        ->and($row['outstanding'])->toBe(1_000_000.0)
        ->and(collect($inventory)->firstWhere('consumer', 'Collection Progress and aging')['position_mode'])->toBe('as_of')
        ->and(collect($inventory)->firstWhere('consumer', 'Period-close restatement')['status'])->toBe('no_implicit_restatement');
});
