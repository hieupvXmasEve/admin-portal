<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'intake' => 2026,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);
    $this->billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $this->student->id]);
});

function staffChosenDueCharge(): void
{
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => test()->billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'due-date',
        'source_ref' => 'due-date:'.test()->student->id.':'.uniqid(),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 1_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => test()->student->id,
        'semester_id' => test()->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1_000_000,
        'description' => 'Staff chosen due date',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
}

it('does not invent a due date when generating a new invoice', function (): void {
    staffChosenDueCharge();

    $invoice = app(InvoiceGenerationService::class)
        ->generateInvoice(test()->student->id, test()->semester->id);

    expect($invoice->due_date)->toBeNull()
        ->and($invoice->real_time_status)->toBe('open');
});

it('updates an existing invoice when a staff-chosen due date is provided', function (): void {
    staffChosenDueCharge();
    $service = app(InvoiceGenerationService::class);
    $invoice = $service->generateInvoice(test()->student->id, test()->semester->id);
    $chosen = Carbon::parse('2026-10-15');

    $updated = $service->generateInvoice(
        test()->student->id,
        test()->semester->id,
        null,
        $chosen,
    );

    expect($updated->id)->toBe($invoice->id)
        ->and($updated->due_date?->toDateString())->toBe('2026-10-15');
});

it('does not overwrite an existing due date when no staff date is provided', function (): void {
    staffChosenDueCharge();
    $service = app(InvoiceGenerationService::class);
    $service->generateInvoice(
        test()->student->id,
        test()->semester->id,
        null,
        Carbon::parse('2026-10-15'),
    );

    $again = $service->generateInvoice(test()->student->id, test()->semester->id);

    expect($again->due_date?->toDateString())->toBe('2026-10-15');
});
