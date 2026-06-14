<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Services\InvoiceGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * DB-01 / NT2: invoice_lines.amount_snapshot must be frozen at creation. A
 * charge amount change is handled by void + recreate, never by overwriting an
 * existing line's money snapshot (that would silently restate history and can
 * turn a correctly-applied payment into an "overpayment").
 */
function makeSnapshotStudent(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

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

    return [$student, $semester];
}

it('freezes amount_snapshot when a charge amount changes on later refresh', function () {
    [$student, $semester] = makeSnapshotStudent();
    $service = app(InvoiceGenerationService::class);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = $service->generateInvoice($student->id, $semester->id);

    $line = InvoiceLine::where('invoice_id', $invoice->id)->where('charge_id', $charge->id)->firstOrFail();
    expect((float) $line->amount_snapshot)->toBe(10000000.0);

    // Charge amount drifts; a later refresh must NOT restate the frozen snapshot.
    $charge->update(['amount' => 12000000]);
    $service->refreshInvoiceFromCharges($invoice->fresh());

    $line->refresh();
    expect((float) $line->amount_snapshot)->toBe(10000000.0);
});

it('reactivates a previously voided line on refresh without mutating the frozen snapshot', function () {
    [$student, $semester] = makeSnapshotStudent();
    $service = app(InvoiceGenerationService::class);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 9000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = $service->generateInvoice($student->id, $semester->id);
    $line = InvoiceLine::where('invoice_id', $invoice->id)->where('charge_id', $charge->id)->firstOrFail();

    // Simulate the line having been voided during a prior refresh (charge inactive),
    // then the charge coming back active with a drifted amount.
    $line->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => 'test void']);
    $charge->update(['amount' => 15000000]);

    $service->refreshInvoiceFromCharges($invoice->fresh());

    $line->refresh();
    expect($line->status)->toBe('active')
        ->and($line->voided_at)->toBeNull()
        ->and((float) $line->amount_snapshot)->toBe(9000000.0);
});
