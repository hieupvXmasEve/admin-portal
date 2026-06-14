<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
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

    $charge = FinanceCharge::create([
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
