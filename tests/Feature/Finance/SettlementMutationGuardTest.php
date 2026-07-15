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
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 2026,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $this->billingAccount = BillingAccount::query()->where('student_id', $student->id)->sole();
    $this->student = $student;
    $this->semester = $semester;
    $this->guard = app(SettlementMutationGuard::class);
});

it('advances once for nested committed mutations', function (): void {
    $this->guard->handleIfChanged(
        $this->billingAccount->id,
        function (BillingAccount $billingAccount): void {
            $this->guard->handle($billingAccount->id, function (): void {});
            $this->guard->handle($billingAccount->id, function (): void {});
        },
    );

    expect((int) $this->billingAccount->fresh()->settlement_version)->toBe(1);
});

it('does not advance for an unmarked no-op', function (): void {
    $this->guard->handleIfChanged($this->billingAccount->id, function (): void {});

    expect((int) $this->billingAccount->fresh()->settlement_version)->toBe(0);
});

it('rolls back the mutation and settlement version together', function (): void {
    expect(fn () => $this->guard->handle(
        $this->billingAccount->id,
        function (BillingAccount $billingAccount): void {
            $billingAccount->update(['settlement_version' => 100]);

            throw new RuntimeException('rollback');
        },
    ))->toThrow(RuntimeException::class, 'rollback');

    expect((int) $this->billingAccount->fresh()->settlement_version)->toBe(0);
});

it('advances once for invoice materialization and not for an unchanged refresh', function (): void {
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $this->billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'guard_invoice',
        'source_ref' => 'guard-invoice:'.$this->student->id,
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
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 1_000_000,
        'description' => 'Guard invoice test',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $versionBefore = (int) $this->billingAccount->fresh()->settlement_version;

    $service = app(InvoiceGenerationService::class);
    $invoice = $service->generateInvoice($this->student->id, $this->semester->id);

    expect((int) $this->billingAccount->fresh()->settlement_version)->toBe($versionBefore + 1);

    $service->generateInvoice($this->student->id, $this->semester->id);

    expect((int) $this->billingAccount->fresh()->settlement_version)->toBe($versionBefore + 1)
        ->and(InvoiceLine::query()->where('invoice_id', $invoice->id)->count())->toBe(1);
});

it('advances once when the paid cache changes and not when it is already current', function (): void {
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $this->billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'guard_paid_cache',
        'source_ref' => 'guard-paid-cache:'.$this->student->id,
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 500_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 500_000,
        'description' => 'Paid cache test',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $invoice = app(InvoiceGenerationService::class)->generateInvoice($this->student->id, $this->semester->id);
    $line = InvoiceLine::query()->where('invoice_id', $invoice->id)->sole();
    $payment = Payment::query()->create([
        'student_id' => $this->student->id,
        'amount' => 500_000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 500_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);
    $invoice->forceFill(['cached_paid_amount' => 0])->save();
    $versionBefore = (int) $this->billingAccount->fresh()->settlement_version;

    $service = app(SettlementService::class);
    $service->recalculateInvoiceSnapshot($invoice->fresh());

    expect((int) $this->billingAccount->fresh()->settlement_version)->toBe($versionBefore + 1)
        ->and((float) $invoice->fresh()->cached_paid_amount)->toBe(500_000.0);

    $service->recalculateInvoiceSnapshot($invoice->fresh());

    expect((int) $this->billingAccount->fresh()->settlement_version)->toBe($versionBefore + 1);
});
