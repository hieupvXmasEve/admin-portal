<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;

/**
 * Shared fixtures for the finance integrity engine tests (auditor + command
 * parity). Guarded with function_exists so multiple test files can require_once
 * this in a single Pest process. A valid student needs intake fields that have
 * no usable DB default, so each helper sets them explicitly.
 */
if (! function_exists('auditStudent')) {
    function auditStudent(): Student
    {
        $semester = Semester::factory()->create();

        return Student::factory()
            ->forCampus(Campus::factory()->create())
            ->forProgram(Program::factory()->create())
            ->state(['intake' => 1, 'intake_semester_id' => $semester->id])
            ->create();
    }
}

if (! function_exists('makeInvoiceLineForStudent')) {
    function makeInvoiceLineForStudent(Student $student): InvoiceLine
    {
        $semester = Semester::factory()->create();

        $charge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => 1000,
            'description' => 'Audit fixture charge',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);

        $invoice = StudentInvoice::create([
            'invoice_number' => 'INV-AUD-'.$student->id.'-'.$charge->id,
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'subtotal' => 1000,
            'discount_total' => 0,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        return InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => 1000,
            'description_snapshot' => 'Audit fixture line',
            'status' => 'active',
        ]);
    }
}

if (! function_exists('seedOverAllocatedPayment')) {
    function seedOverAllocatedPayment(Student $student): Payment
    {
        // Payment whose applications sum (250) exceeds its amount (100) -> violates INV-1.
        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => 100,
            'method' => Payment::METHOD_GATEWAY,
            'source' => 'manual',
            'paid_at' => now(),
            'status' => Payment::STATUS_COMPLETED,
        ]);

        PaymentApplication::create([
            'payment_id' => $payment->id,
            'invoice_line_id' => makeInvoiceLineForStudent($student)->id,
            'amount' => 250,
            'entry_type' => 'application',
            'applied_at' => now(),
        ]);

        return $payment;
    }
}
