<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sorts outstanding lines by configured charge-type priority before due date', function () {
    $semester = Semester::factory()->create();

    $student = Student::factory()->state([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
        'status' => 'intake_course',
    ])->create();

    $tuitionCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $retakeCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 1000000,
        'description' => 'Retake',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $tuitionInvoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'invoice_number' => 'INV-TUITION',
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    $retakeInvoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'invoice_number' => 'INV-RETAKE',
        'status' => 'pending',
        'due_date' => now()->addDay(),
    ]);

    InvoiceLine::create([
        'invoice_id' => $tuitionInvoice->id,
        'charge_id' => $tuitionCharge->id,
        'amount_snapshot' => 5000000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    InvoiceLine::create([
        'invoice_id' => $retakeInvoice->id,
        'charge_id' => $retakeCharge->id,
        'amount_snapshot' => 1000000,
        'description_snapshot' => 'Retake',
        'status' => 'active',
    ]);

    $lines = app(SettlementService::class)
        ->getOutstandingLinesForStudent($student->id, ['retake_fee', 'tuition_term']);

    expect($lines->first()?->charge?->charge_type)->toBe('retake_fee');
});
