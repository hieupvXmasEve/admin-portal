<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Support\Integrity\FinanceInvariantSampleResolver;

it('maps each invariant code to its audit target type or a list view', function () {
    $resolver = new FinanceInvariantSampleResolver;

    expect($resolver->targetTypeFor('INV-1'))->toBe('payment');
    expect($resolver->targetTypeFor('INV-3'))->toBe('charge');
    expect($resolver->targetTypeFor('INV-2'))->toBe('invoice');
    expect($resolver->targetTypeFor('INV-12'))->toBe('dng');
    expect($resolver->targetTypeFor('INV-4'))->toBe('invoice');
    expect($resolver->targetTypeFor('INV-5'))->toBe('invoice');
    expect($resolver->targetTypeFor('INV-7'))->toBe('student');
    expect($resolver->targetTypeFor('INV-11'))->toBeNull();
    expect($resolver->targetTypeFor('INV-15'))->toBe('dng');
    expect($resolver->listUrlFor('INV-11'))->not->toBeNull();
});

it('resolves invoice_line sample ids to invoice targets', function () {
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->forProgram($program)
        ->state(['intake' => 1, 'intake_semester_id' => $semester->id])
        ->create();
    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 100,
        'description' => 'Drilldown fixture charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $invoice = StudentInvoice::create([
        'invoice_number' => "INV-DRILL-{$student->id}-{$semester->id}",
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
        'subtotal' => 100,
        'discount_total' => 0,
        'total_amount' => 100,
        'paid_amount' => 0,
    ]);
    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'description_snapshot' => 'Test line',
        'amount_snapshot' => 100,
        'status' => 'active',
    ]);

    $resolver = new FinanceInvariantSampleResolver;

    expect($resolver->resolveTargetId('INV-4', (int) $line->id))->toBe((int) $invoice->id);
});
