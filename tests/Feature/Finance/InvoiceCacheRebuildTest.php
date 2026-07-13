<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function makeCacheInvoice(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-CACHE-'.$student->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    $obligation = FinanceObligation::create([
        'source_system' => 'test',
        'source_kind' => 'invoice-cache',
        'source_ref' => 'invoice-cache:'.$student->id,
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 10000000,
        'currency' => 'VND',
        'pricing_rule_version' => 'invoice-cache:test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 10000000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    return [$invoice, $line, $student];
}

function applyCacheCredit(InvoiceLine $line, float $amount): CreditApplication
{
    $entitlement = FinanceCreditEntitlement::create([
        'source_system' => 'test',
        'source_kind' => 'invoice-cache-credit',
        'source_ref' => 'invoice-cache-credit:'.$line->id,
        'entitlement_type' => FinanceCharge::TYPE_DEFER_CREDIT,
        'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
        'allocation_status' => FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'invoice-cache:test',
        'pricing_snapshot' => [],
        'approved_at' => now(),
    ]);

    return CreditApplication::create([
        'finance_credit_entitlement_id' => $entitlement->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => CreditApplication::ENTRY_APPLICATION,
        'applied_at' => now(),
    ]);
}

it('renames snapshot columns to cached_* and keeps legacy attribute names working', function () {
    expect(Schema::hasColumn('student_invoices', 'cached_total_amount'))->toBeTrue()
        ->and(Schema::hasColumn('student_invoices', 'cached_paid_at'))->toBeTrue()
        ->and(Schema::hasColumn('student_invoices', 'total_amount'))->toBeFalse();

    [$invoice] = makeCacheInvoice();

    // Legacy write name maps onto the cache column transparently.
    $invoice->forceFill(['subtotal' => 123, 'total_amount' => 456])->save();
    expect((float) $invoice->fresh()->cached_subtotal)->toBe(123.0)
        ->and((float) $invoice->fresh()->cached_total_amount)->toBe(456.0);
});

it('rebuilds cached_* from the ledger, correcting a drifted cache', function () {
    [$invoice, $line] = makeCacheInvoice();

    // Corrupt the cache directly in the DB (simulating drift).
    DB::table('student_invoices')->where('id', $invoice->id)->update([
        'cached_total_amount' => 999999,
        'cached_paid_amount' => 888888,
    ]);

    $this->artisan('finance:rebuild-invoice-snapshots')
        ->assertSuccessful();

    $fresh = DB::table('student_invoices')->where('id', $invoice->id)->first();

    expect((float) $fresh->cached_total_amount)->toBe(10000000.0)
        ->and((float) $fresh->cached_paid_amount)->toBe(0.0);
});

it('stores matched cash only when credit also settles the invoice', function () {
    [$invoice, $line, $student] = makeCacheInvoice();
    $settlement = app(SettlementService::class);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 3000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);
    $settlement->createPaymentApplication($payment, $line, 3000000, 'application');
    applyCacheCredit($line, 7000000);
    $settlement->recalculateInvoiceSnapshot($invoice->fresh());

    $fresh = $invoice->fresh();
    expect((float) $fresh->cached_paid_amount)->toBe(3000000.0)
        ->and((float) $fresh->paid_amount)->toBe(3000000.0)
        ->and($fresh->status)->toBe('paid')
        ->and($fresh->cached_paid_at)->not->toBeNull();
});

it('does not create a cash paid timestamp for a credit-only settlement', function () {
    [$invoice, $line] = makeCacheInvoice();
    applyCacheCredit($line, 10000000);

    app(SettlementService::class)->recalculateInvoiceSnapshot($invoice->fresh());

    $fresh = $invoice->fresh();
    expect((float) $fresh->cached_paid_amount)->toBe(0.0)
        ->and($fresh->status)->toBe('paid')
        ->and($fresh->cached_paid_at)->toBeNull();
});

it('preserves a draft invoice lifecycle when its due date is in the past', function () {
    [$invoice] = makeCacheInvoice();
    $invoice->forceFill([
        'status' => 'draft',
        'due_date' => now()->subDay(),
    ])->save();

    app(SettlementService::class)->recalculateInvoiceSnapshot($invoice->fresh());

    expect($invoice->fresh()->status)->toBe('draft');
});

it('reconstructs a missing cash paid timestamp from application evidence', function () {
    [$invoice, $line, $student] = makeCacheInvoice();
    $historicalPaidAt = now()->subMonths(2)->startOfSecond();
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 10000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => $historicalPaidAt,
        'source' => 'manual',
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 10000000,
        'entry_type' => 'application',
        'applied_at' => $historicalPaidAt,
    ]);
    $invoice->forceFill([
        'status' => 'partial',
        'cached_paid_at' => null,
    ])->save();

    app(SettlementService::class)->recalculateInvoiceSnapshot($invoice->fresh());

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe('paid')
        ->and($fresh->cached_paid_at?->equalTo($historicalPaidAt))->toBeTrue();
});

it('clears the cash-only cache when a fully paid line is voided', function () {
    [$invoice, $line, $student] = makeCacheInvoice();
    $settlement = app(SettlementService::class);
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 10000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);
    $settlement->createPaymentApplication($payment, $line, 10000000, 'application');

    app(VoidFinanceChargeAction::class)->handle(
        (int) $line->charge_id,
        'Paid line voided for cache parity test',
        User::factory()->create()->id,
        false,
    );

    $fresh = $invoice->fresh();
    expect($fresh->status)->toBe('cancelled')
        ->and((float) $fresh->cached_paid_amount)->toBe(0.0)
        ->and((float) $fresh->cached_total_amount)->toBe(0.0)
        ->and($fresh->cached_paid_at)->toBeNull();
});

it('dry-runs scoped cache rebuilds without writing and reports cash-only drift evidence', function () {
    [$invoice] = makeCacheInvoice();
    DB::table('student_invoices')->where('id', $invoice->id)->update([
        'cached_total_amount' => 999999,
        'cached_paid_amount' => 888888,
    ]);

    $this->artisan('finance:rebuild-invoice-snapshots', [
        '--semester' => $invoice->semester_id,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Drifted: 1')
        ->assertSuccessful();

    expect((float) $invoice->fresh()->cached_paid_amount)->toBe(888888.0);

    $this->artisan('finance:rebuild-invoice-snapshots', [
        '--semester' => $invoice->semester_id,
    ])->assertSuccessful();

    $this->artisan('finance:rebuild-invoice-snapshots', [
        '--semester' => $invoice->semester_id,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Drifted: 0')
        ->assertSuccessful();
});

it('does not resurrect a cancelled invoice during rebuild (status is lifecycle, not cache)', function () {
    [$invoice, $line] = makeCacheInvoice();

    // A cancelled invoice still has active lines/balance in the ledger, so the
    // derived payment status would be "pending" — the rebuild must not apply it.
    $invoice->update(['status' => 'cancelled']);

    $this->artisan('finance:rebuild-invoice-snapshots')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe('cancelled');

    // Direct recalc must preserve it too (recalc runs from many call sites).
    app(SettlementService::class)->recalculateInvoiceSnapshot($invoice->fresh());
    expect($invoice->fresh()->status)->toBe('cancelled');
});

it('clears cached_paid_at when a reversal makes a paid invoice no longer paid (DB-15)', function () {
    [$invoice, $line, $student] = makeCacheInvoice();
    $settlement = app(SettlementService::class);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 10000000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $settlement->createPaymentApplication($payment, $line, 10000000, 'application');

    $invoice->refresh();
    expect($invoice->status)->toBe('paid')
        ->and($invoice->cached_paid_at)->not->toBeNull();

    // Reverse the payment: invoice is no longer paid -> cached_paid_at must clear.
    $settlement->releaseLinePayments($line);

    $invoice->refresh();
    expect($invoice->status)->not->toBe('paid')
        ->and($invoice->cached_paid_at)->toBeNull()
        ->and($invoice->paid_at)->toBeNull();
});
