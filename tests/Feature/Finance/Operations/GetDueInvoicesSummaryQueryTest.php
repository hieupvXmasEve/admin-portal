<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Operations\GetDueInvoicesSummaryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('computes overdue amount from settlement snapshots without per-invoice accessor n+1', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
            'status' => 'intake_course',
        ])
        ->create();

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 3000000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'invoice_number' => 'INV-OVERDUE',
        'status' => 'pending',
        'due_date' => now()->subDay(),
        'subtotal' => 3000000,
        'discount_total' => 0,
        'total_amount' => 3000000,
        'paid_amount' => 0,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 3000000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $summary = app(GetDueInvoicesSummaryQuery::class)->handle($semester->id);
    $queryCount = count(DB::getQueryLog());

    expect($summary['overdue_count'])->toBe(1)
        ->and($summary['total_overdue_amount'])->toBe(3000000.0)
        ->and($queryCount)->toBeLessThan(12);
});
