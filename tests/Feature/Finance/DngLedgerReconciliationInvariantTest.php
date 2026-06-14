<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * DB-13: the DNG provider rail must reconcile to the ledger. INV-12 proves that
 * a DNG request bridged to a canonical Payment carries the same amount as that
 * payment, so KPI code cannot double-count the same obligation across the two
 * rails. This is a read-only proof; it does not change KPI aggregation.
 */
function makeReconStudent(): Student
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    return Student::factory()
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
}

function makeBridgedDngRequest(Student $student, float $requestAmount, float $paymentAmount): DngPaymentRequest
{
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => $paymentAmount,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'dng',
        'external_ref' => 'DNGPAY-'.$student->id,
    ]);

    return DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU'.$student->id,
        'fee_type' => 'tuition',
        'item_id' => 'ITEM-'.$student->id,
        'amount' => $requestAmount,
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'dng_payment_id' => 'DNGPAY-'.$student->id,
        'payment_id' => $payment->id,
    ]);
}

it('passes the audit (incl. INV-12) when a bridged DNG request matches its canonical payment', function () {
    $student = makeReconStudent();
    makeBridgedDngRequest($student, 5000000, 5000000);

    $this->artisan('finance:audit-invariants')
        ->assertSuccessful()
        ->expectsOutputToContain('INV-12')
        ->expectsOutputToContain('All invariants pass');
});

it('flags INV-12 when a bridged DNG request amount diverges from its canonical payment', function () {
    $student = makeReconStudent();
    makeBridgedDngRequest($student, 5000000, 3000000); // rail mismatch

    $this->artisan('finance:audit-invariants', ['--sample' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('INV-12')
        ->expectsOutputToContain('total offending');
});
